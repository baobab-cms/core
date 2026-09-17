<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Baobab\Queue\Actions\DetectStaleQueueWorker;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « Queues » (spec 12 §7.2) — réutilise `DetectStaleQueueWorker`
 * (extrait de `QueuesController`, M9 chantier 0.a Pass C) : même seuil,
 * même détection que le bandeau déjà affiché sur `admin/system/queues`,
 * une seule source de vérité.
 */
final class QueueWorkerCheck extends Check
{
    public function __construct(private readonly DetectStaleQueueWorker $detect)
    {
        parent::__construct();
    }

    public function run(): Result
    {
        $minutes = ($this->detect)();
        $result = Result::make();

        if ($minutes === null) {
            return $result->ok();
        }

        return $result->warning("Aucun job consommé depuis {$minutes} minutes — le worker semble à l'arrêt.");
    }
}
