<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\System\Actions\RunHealthChecks;
use Illuminate\Console\Command;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;

/**
 * `php artisan baobab:health` (spec 12 §11) — sortie table + code retour,
 * distincte de `health:check` natif : passe par `RunHealthChecks` pour que
 * le passage en échec déclenche le journal technique et la notification
 * Baobab (§7.3), jamais le canal natif du package.
 */
final class HealthCheckCommand extends Command
{
    protected $signature = 'baobab:health';

    protected $description = 'Lance les contrôles de santé (spec 12 §7.2) et affiche leur résultat.';

    public function handle(RunHealthChecks $action): int
    {
        $results = $action();

        foreach ($results->storedCheckResults as $result) {
            /** @var StoredCheckResult $result */
            $this->components->twoColumnDetail(
                $result->label.($result->shortSummary === '' ? '' : ' <fg=gray>'.$result->shortSummary.'</>'),
                $this->statusBadge($result->status),
            );
        }

        return $results->allChecksOk() ? self::SUCCESS : self::FAILURE;
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            'ok' => '<fg=#1E9079>OK</>',
            'warning' => '<fg=#E9A13B>AVERTISSEMENT</>',
            default => '<fg=red>ÉCHEC</>',
        };
    }
}
