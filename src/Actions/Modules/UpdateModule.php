<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;

/**
 * Met à jour un module dont le code a changé de version sur disque
 * (spec-modules §3, étape « Mise à jour » ; suivi n° 156).
 *
 * # Le défaut qu'elle ferme
 *
 * Trois endroits seulement exécutaient `migrate` dans tout le Core :
 * `InstallModule`, une fois, à l'installation ; `EvolveContentType`, sur le
 * chemin Content Type ; et `EvolveModuleSchema`, qui part d'un
 * `ModuleBlueprintDraft` et ne sert donc **que** le Studio. Aucun ne couvrait le
 * cas d'un module dont le code a changé sur disque — `composer update`, ou une
 * archive plus récente déposée à la main.
 *
 * Conséquence : `SyncModuleManifest` rafraîchissait bien permissions, menus et
 * déclarations, mais la table restait dans son état de la version précédente. Le
 * module était **à moitié à jour, et rien ne le signalait**.
 *
 * # Ce qu'elle est
 *
 * Une composition, pas une quatrième mécanique : les migrations en attente,
 * **puis** `SyncModuleManifest`. L'ordre compte et il est le même qu'au Studio —
 * le schéma d'abord, le manifeste ensuite : une permission qui gouverne un écran
 * dont la table n'existe pas encore serait vraie un instant trop tôt.
 *
 * # Ce qui la déclenche
 *
 * L'existence de **migrations en attente**, jamais la comparaison des numéros de
 * version. Un module peut changer de code sans changer de version — c'est même le
 * quotidien d'un développement local — et un module peut changer de version sans
 * nouvelle migration. Le numéro de version est une déclaration ; la table
 * `migrations` est un fait.
 *
 * # Ce qu'elle n'est pas
 *
 * Le **canal** qui amène une nouvelle version sur le serveur — détection des
 * versions disponibles, notification, marketplace, signature — reste un chantier
 * distinct de la phase Plateforme (registre §4.2, point S). Cette Action suppose
 * le code déjà en place et ne va jamais le chercher.
 */
final class UpdateModule
{
    public function __construct(
        private readonly SyncModuleManifest $syncManifest,
        private readonly Migrator $migrator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{
     *     migrations: list<string>,
     *     manifest: array{manifest_changed: bool, permissions: array{added: list<string>, updated: list<string>, removed: list<string>}, menu_items: int},
     * }
     *
     * @throws ModuleNotFoundException
     */
    public function __invoke(Module $module, bool $confirmDestructive = false): array
    {
        $pending = $this->pendingMigrations($module);

        if ($pending !== []) {
            Artisan::call('migrate', [
                '--path' => $module->path.'/database/migrations',
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        // La resynchronisation porte ses propres gardes — dont le refus de
        // retirer une permission sans confirmation. On ne les double pas ici :
        // elles sont vérifiées là où elles se testent.
        $manifest = ($this->syncManifest)($module, $confirmDestructive);

        $result = ['migrations' => $pending, 'manifest' => $manifest];

        if ($pending === [] && ! $manifest['manifest_changed']) {
            return $result;
        }

        $this->audit->record('module.updated', $module, $result);

        Hook::action('baobab.module.updated', $module, $result);

        return $result;
    }

    /**
     * Migrations du module présentes sur disque et absentes de la table
     * `migrations` — donc jamais jouées sur cette installation.
     *
     * Calculées **avant** l'appel à `migrate`, pour pouvoir dire ce qui a été
     * joué plutôt qu'un « Terminé ✓ » : après coup, la commande ne distingue plus
     * ce qu'elle vient de faire de ce qui était déjà là.
     *
     * `Migrator::pendingMigrations()` ferait exactement ce calcul mais est
     * `protected` ; on le refait donc avec les deux méthodes publiques qui le
     * composent, plutôt que d'exposer le moteur de migrations par un détour.
     *
     * @return list<string>
     */
    private function pendingMigrations(Module $module): array
    {
        $path = $module->path.'/database/migrations';

        if (! is_dir($path)) {
            return [];
        }

        $ran = $this->migrator->getRepository()->getRan();

        $pending = [];

        foreach ($this->migrator->getMigrationFiles([$path]) as $file) {
            $name = $this->migrator->getMigrationName($file);

            if (! in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }
}
