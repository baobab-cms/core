<?php

declare(strict_types=1);

namespace Baobab\Modules\Support;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;

/**
 * Défaire les migrations d'un module, où que soient ses fichiers.
 *
 * Extrait de `UninstallModule` le 24 août 2026 en lui donnant un second
 * consommateur : la compensation d'une génération Studio ratée
 * (`GenerateModuleFromDraft`, suivi n° 148) doit défaire des migrations
 * **sans qu'aucune ligne `modules` n'existe** — `InstallModule` les joue avant
 * de créer la ligne, si bien qu'un échec de migration laisse des tables
 * derrière lui et rien en base pour les désigner. Dupliquer l'appel aurait
 * laissé deux versions d'un raisonnement qui a déjà coûté un défaut.
 */
final class ModuleMigrations
{
    public function __construct(private readonly Migrator $migrator) {}

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
    public function rollback(string $modulePath): void
    {
        $migrationsPath = $modulePath.'/database/migrations';

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
}
