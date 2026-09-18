<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\ModuleStillActiveException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleUploadPaths;
use Baobab\Modules\Support\ModuleMigrations;
use Baobab\Themes\Actions\UnpublishThemeAssets;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * Désinstalle un module inactif (spec 01 §3). Avec `$purge`, rejoue le
 * rollback de ses migrations avant de supprimer la ligne modules (les
 * permissions et menus associés suivent par cascade DB). Avec `$deleteFiles`,
 * retire aussi le code du module du disque.
 *
 * Quatre nettoyages, quatre portées différentes, et c'est voulu :
 *
 * - **les assets publiés** partent toujours — ce sont des artefacts dérivés,
 *   les garder n'a aucun sens une fois le module parti (spec §3, écart comblé
 *   le 10 août 2026 : les trois autres nettoyages existaient depuis M1, pas
 *   celui-là) ;
 * - **les données** (`$purge`) ne partent que sur demande explicite, la spec
 *   exigeant une « confirmation explicite » ;
 * - **les fichiers** (`$deleteFiles`) ne partent que sur demande explicite, et
 *   jamais pour un module installé par Composer : `vendor/` appartient à
 *   Composer, pas au CMS ;
 * - **la ligne `content_types`** d'un module Content Type (`$module->type ===
 *   'content-type'`) ne part que si `$purge` **et** `$deleteFiles` sont tous
 *   les deux cochés (gap trouvé en vérifiant la Pass F2 — spec 12 §5 — en
 *   navigateur, suivi n° 329) : `module_id` passe déjà à `null` par la seule
 *   FK (`nullOnDelete()`), mais la ligne elle-même survivait à `$purge` seul,
 *   orpheline (`table_name` pointant vers une table que `$purge` vient de
 *   `DROP`). Restreint à la combinaison des deux cases plutôt qu'à `$purge`
 *   seul : `$purge` sans `$deleteFiles` garde le code du module sur le
 *   disque, un cas où la ligne pourrait encore servir à une réinstallation
 *   depuis les mêmes fichiers (rapproché du n° 82, gap distinct et non
 *   traité ici) — seule la combinaison des deux, qui ne laisse plus rien du
 *   module nulle part, justifie de ne plus rien laisser non plus dans
 *   `content_types`.
 */
final class UninstallModule
{
    public function __construct(
        private readonly ModuleMigrations $migrations,
        private readonly UnpublishThemeAssets $unpublishAssets,
    ) {}

    public function __invoke(string $name, bool $purge = false, bool $deleteFiles = false): void
    {
        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            throw ModuleNotFoundException::named($name);
        }

        if ($module->status === 'active') {
            throw ModuleStillActiveException::named($name);
        }

        if ($purge) {
            $this->migrations->rollback((string) $module->path);
            $this->purgePermissions($module);
        }

        ($this->unpublishAssets)($module);

        // Après le rollback : les migrations ne peuvent être défaites que tant
        // que leurs fichiers sont là.
        if ($deleteFiles) {
            $this->deleteFiles($module);
        }

        // Avant $module->delete() : la FK nullOnDelete() met module_id à null
        // dès la suppression de la ligne modules, la ligne content_types ne
        // serait alors plus retrouvable par cette colonne.
        if ($purge && $deleteFiles && $module->type === 'content-type') {
            $this->purgeContentType($module);
        }

        $snapshot = $module->only(['name', 'title', 'version']);

        $module->delete();

        Hook::action('baobab.module.uninstalled', $snapshot);
    }

    /**
     * Supprimer des fichiers d'après un chemin lu en base mérite deux verrous,
     * parce qu'une ligne `modules` compromise deviendrait sinon une
     * suppression arbitraire sur le serveur :
     *
     * 1. la source doit être `local` — un module Composer n'est jamais à nous ;
     * 2. le chemin doit être **contenu** dans une des racines d'upload
     *    configurées, comparé après résolution des liens et des `..`.
     *
     * Un refus est silencieux à dessein : la désinstallation elle-même a
     * réussi, et rien ne justifie de la faire échouer parce que le ménage
     * n'était pas permis. `ModuleInventory` signalera de toute façon un module
     * dont les fichiers sont restés.
     */
    private function deleteFiles(Module $module): void
    {
        if ($module->source !== 'local') {
            return;
        }

        $path = realpath($module->path);

        if ($path === false) {
            return;
        }

        foreach (ModuleUploadPaths::roots() as $root) {
            $resolvedRoot = realpath($root);

            // `ModuleUploadPaths` garantit des racines non vides, et c'est
            // essentiel ici : `realpath('')` retourne le répertoire de travail
            // courant, ce qui transformerait ce verrou en autorisation quasi
            // générale (défaut relevé le 10 août 2026). Une racine qui ne se
            // résout pas — dossier jamais créé — n'autorise rien non plus.
            if ($resolvedRoot === false) {
                continue;
            }

            if (str_starts_with($path, $resolvedRoot.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($path);

                return;
            }
        }
    }

    private function purgeContentType(Module $module): void
    {
        ContentType::where('module_id', $module->id)->delete();
    }

    private function purgePermissions(Module $module): void
    {
        $keys = $module->permissions->pluck('key');

        if ($keys->isEmpty()) {
            return;
        }

        Permission::whereIn('name', $keys)->where('guard_name', 'baobab')->delete();
    }
}
