<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « Espace disque » (spec 12 §7.2) — `disk_free_space()`/
 * `disk_total_space()` natifs plutôt que `Spatie\Health\Checks\Checks\
 * UsedDiskSpaceCheck` : ce dernier shelle vers `df -P`, absent sous
 * Windows (poste de développement de ce projet) et non portable en test.
 */
final class DiskSpaceCheck extends Check
{
    public function run(): Result
    {
        $path = base_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        $result = Result::make();

        if ($free === false || $total === false || $total <= 0) {
            return $result->warning('Espace disque non mesurable sur cet environnement.');
        }

        $usedPercent = (int) round((1 - $free / $total) * 100);
        $result->shortSummary($usedPercent.'%');

        $warningPercent = (int) config('baobab.health.disk_space.warning_percent', 80);
        $failingPercent = (int) config('baobab.health.disk_space.failing_percent', 90);

        if ($usedPercent >= $failingPercent) {
            return $result->failed("Espace disque presque plein ({$usedPercent}% utilisé).");
        }

        if ($usedPercent >= $warningPercent) {
            return $result->warning("Espace disque proche du seuil ({$usedPercent}% utilisé).");
        }

        return $result->ok();
    }
}
