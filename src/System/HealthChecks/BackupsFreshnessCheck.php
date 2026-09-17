<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Baobab\Backups\Actions\ListBackups;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « Sauvegardes » (spec 12 §7.2) — réutilise `ListBackups`
 * (extrait de `BackupsController`, M9 chantier 0.a Pass D) : même source
 * que l'écran `admin/system/backups`, jamais le `BackupsCheck` natif de
 * `spatie/laravel-health` (lit `config('backup.monitor_backups')`, une
 * config figée au boot — incompatible avec le choix déjà fait en Pass D
 * de résoudre `backup.*` à la volée depuis `baobab.backups.*` au moment de
 * chaque sauvegarde, jamais durablement).
 */
final class BackupsFreshnessCheck extends Check
{
    public function __construct(private readonly ListBackups $listBackups)
    {
        parent::__construct();
    }

    public function run(): Result
    {
        $backups = ($this->listBackups)();
        $result = Result::make();

        if ($backups === []) {
            return $result->warning('Aucune sauvegarde trouvée.');
        }

        $latest = $backups[0]['date'];
        $hours = $latest->diffInHours(now());
        $result->shortSummary($hours.' h');

        $windowHours = (int) config('baobab.health.backups.expected_within_hours', 48);

        if ($hours > $windowHours) {
            return $result->failed("Dernière sauvegarde il y a {$hours} heures (fenêtre attendue : {$windowHours} h).");
        }

        return $result->ok();
    }
}
