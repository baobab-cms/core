<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Baobab\Install\Actions\CheckRequirements;
use Baobab\Install\Requirement;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « Stockage » (spec 12 §7.2) — réutilise `CheckRequirements`
 * (spec 15, installateur), déjà conçue pour être relancée après coup
 * (`baobab:check`). Mêmes trois répertoires que `CheckCommand` (`storage`,
 * `bootstrap/cache`, `public`) — pas `modules`/`themes` malgré la spec 15
 * §4 qui les nomme : `baobab:check` ne les vérifie déjà pas, ce contrôle
 * s'aligne sur l'existant plutôt que de rouvrir cet écart, hors périmètre
 * de la Pass E.
 */
final class StorageCheck extends Check
{
    public function __construct(private readonly CheckRequirements $checkRequirements)
    {
        parent::__construct();
    }

    public function run(): Result
    {
        $report = ($this->checkRequirements)(public_path(), [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public' => public_path(),
        ]);

        $failures = array_values(array_filter(
            $report->requirements,
            fn (Requirement $requirement): bool => str_starts_with($requirement->key, 'writable.') && ! $requirement->satisfied,
        ));

        $result = Result::make();

        if ($failures !== []) {
            $labels = implode(', ', array_map(fn (Requirement $requirement): string => $requirement->label, $failures));

            return $result->failed("{$labels} non accessible(s) en écriture.");
        }

        return $result->ok();
    }
}
