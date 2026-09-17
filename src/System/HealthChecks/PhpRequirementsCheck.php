<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Baobab\Install\Actions\CheckRequirements;
use Baobab\Install\Requirement;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « PHP » (spec 12 §7.2) — réutilise `CheckRequirements` (spec
 * 15) : version + extensions, jamais rejoué à la main. Aucun répertoire
 * transmis, donc aucune exigence `writable.*` dans le rapport — seuls le
 * couple version/extensions comptent ici, le reste est du ressort de
 * `StorageCheck`.
 */
final class PhpRequirementsCheck extends Check
{
    public function __construct(private readonly CheckRequirements $checkRequirements)
    {
        parent::__construct();
    }

    public function run(): Result
    {
        $report = ($this->checkRequirements)(public_path(), []);

        $failures = array_values(array_filter(
            $report->requirements,
            fn (Requirement $requirement): bool => $requirement->isBlockingFailure(),
        ));

        $result = Result::make()->shortSummary(PHP_VERSION);

        if ($failures !== []) {
            $labels = implode(', ', array_map(fn (Requirement $requirement): string => $requirement->label, $failures));

            return $result->failed("{$labels} manquant(s).");
        }

        return $result->ok();
    }
}
