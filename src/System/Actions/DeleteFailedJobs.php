<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Illuminate\Support\Facades\Artisan;

/**
 * Supprime un ou plusieurs jobs échoués (spec 12 §3.2) — `queue:forget`,
 * patron `RetryFailedJobs`. Contrairement à `queue:retry`, la commande
 * native n'accepte qu'un seul `id` par appel (`{id : The ID of the failed
 * job}`) — une commande par UUID plutôt qu'un seul appel groupé.
 */
final class DeleteFailedJobs
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<string>  $uuids
     */
    public function __invoke(array $uuids): void
    {
        if ($uuids === []) {
            return;
        }

        foreach ($uuids as $uuid) {
            Artisan::call('queue:forget', ['id' => $uuid]);
        }

        $this->audit->record('queue.jobs.deleted', null, ['uuids' => $uuids, 'count' => count($uuids)]);
    }
}
