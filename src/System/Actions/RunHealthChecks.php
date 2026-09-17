<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Facades\Hook;
use Baobab\Notify\Notifier;
use Baobab\Support\Logger;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;

/**
 * Exécute les 9 contrôles v1 (spec 12 §7.2), à la demande ou depuis la
 * tâche planifiée — wrappe `health:check` de `spatie/laravel-health`,
 * jamais réimplémenté (patron `CreateBackup`, `Artisan::call`).
 * `--no-notification` désactive le canal mail natif du package : le
 * passage en échec (spec §7.3) passe par le journal technique et la
 * notification Baobab (patron exact `CreateBackup`), jamais celui de
 * spatie/laravel-health.
 */
final class RunHealthChecks
{
    public function __construct(
        private readonly Logger $logger,
        private readonly Notifier $notifier,
    ) {}

    public function __invoke(): StoredCheckResults
    {
        Artisan::call('health:check', ['--no-notification' => true]);

        $results = Health::resultStores()->first()?->latestResults();

        if (! $results instanceof StoredCheckResults) {
            throw new RuntimeException('health:check did not store any result.');
        }

        // `containsFailingCheck()` compte aussi un simple `warning` comme
        // échec (tout statut hors `ok`) — la spec §7.3 ne notifie que le
        // vrai passage en « failing », jamais un avertissement.
        if ($results->containsCheckWithStatus(Status::failed())) {
            $this->reportFailure($results);
        }

        // Déclenché à chaque exécution, succès compris — jamais seulement
        // sur échec, contrairement à `baobab.backup.failed` : un
        // « dead man's switch » externe (ex. healthchecks.io, hors
        // périmètre Baobab par la spec §7.3 elle-même) a justement besoin
        // d'un signal à chaque passage pour détecter l'absence de signal,
        // pas seulement l'échec. Patron symétrique à
        // `baobab.backup.completed`/`.failed`.
        Hook::action('baobab.health.checked', $results);

        return $results;
    }

    private function reportFailure(StoredCheckResults $results): void
    {
        $failing = $results->storedCheckResults
            ->filter(fn (StoredCheckResult $result): bool => $result->status === Status::failed()->value)
            ->map(fn (StoredCheckResult $result): string => "{$result->label} : {$result->notificationMessage}")
            ->implode(' | ');

        $this->logger->error('Health check failing.', ['slug' => 'health', 'summary' => $failing]);

        $this->notifier->send(
            'core.health.failing',
            User::permission('baobab.system.health.view')->get(),
            ['summary' => $failing],
        );
    }
}
