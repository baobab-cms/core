<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\ModuleStillActiveException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleUploadPaths;
use Baobab\Themes\Actions\UnpublishThemeAssets;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * Désinstalle un module inactif (spec 01 §3). Avec `$purge`, rejoue le
 * rollback de ses migrations avant de supprimer la ligne modules (les
 * permissions et menus associés suivent par cascade DB). Avec `$deleteFiles`,
 * retire aussi le code du module du disque.
 *
 * Trois nettoyages, trois portées différentes, et c'est voulu :
 *
 * - **les assets publiés** partent toujours — ce sont des artefacts dérivés,
 *   les garder n'a aucun sens une fois le module parti (spec §3, écart comblé
 *   le 10 août 2026 : les trois autres nettoyages existaient depuis M1, pas
 *   celui-là) ;
 * - **les données** (`$purge`) ne partent que sur demande explicite, la spec
 *   exigeant une « confirmation explicite » ;
 * - **les fichiers** (`$deleteFiles`) ne partent que sur demande explicite, et
 *   jamais pour un module installé par Composer : `vendor/` appartient à
 *   Composer, pas au CMS.
 */
final class UninstallModule
{
    public function __construct(
        private readonly Migrator $migrator,
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
            $this->rollbackMigrations($module->path);
            $this->purgePermissions($module);
        }

        ($this->unpublishAssets)($module);

        // Après le rollback : les migrations ne peuvent être défaites que tant
        // que leurs fichiers sont là.
        if ($deleteFiles) {
            $this->deleteFiles($module);
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

    /**
     * `migrate:rollback` ne regarde par défaut que le **dernier lot**. Les
     * migrations d'un module installé avant que quoi que ce soit d'autre ne
     * migre n'y sont plus : sans `--step`, la purge ne défaisait rien du tout,
     * et en silence — les tables du module restaient en base alors que la CLI
     * comme l'écran annonçaient leur suppression. Défaut relevé le 10 août
     * 2026 en vérification navigateur (M8 point 9, Pass A).
     *
     * On demande donc autant d'étapes qu'il y a de migrations jouées. Laravel
     * ignore celles qui ne se trouvent pas dans `--path` (« Migration not
     * found »), ce qui laisse exactement les migrations de ce module, quel que
     * soit le lot dans lequel elles ont été jouées.
     */
    private function rollbackMigrations(string $path): void
    {
        $migrationsPath = $path.'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return;
        }

        Artisan::call('migrate:rollback', [
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
            '--step' => count($this->migrator->getRepository()->getRan()),
        ]);
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
