<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Illuminate\Support\Facades\Artisan;

/**
 * Relance un ou plusieurs jobs échoués (spec 12 §3.2) — `queue:retry` est une
 * commande Laravel native, enregistrée par `ArtisanServiceProvider` (deferred
 * provider), jamais soumise au garde `runningInConsole()` de
 * `BaobabServiceProvider::boot()` : contrairement au bug rencontré en Pass B
 * sur les commandes propres au Core, `Artisan::call()` la résout sans
 * problème depuis une requête HTTP admin. La réutiliser telle quelle
 * (`pushRaw`/reset des tentatives déjà géré par Laravel) plutôt que la
 * réimplémenter est le choix Laravel-first.
 */
final class RetryFailedJobs
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

        Artisan::call('queue:retry', ['id' => $uuids]);

        $this->audit->record('queue.jobs.retried', null, ['uuids' => $uuids, 'count' => count($uuids)]);
    }
}
