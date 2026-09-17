<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Contrôle « Base de données » (spec 12 §7.2) — connexion + latence.
 * `Spatie\Health\Checks\Checks\DatabaseCheck` natif ne mesure que la
 * connexion, jamais la latence explicitement demandée par la spec
 * (« échec, latence anormale ») : contrôle Baobab dédié plutôt qu'un
 * emprunt partiel au check natif.
 */
final class DatabaseConnectionCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('select 1');
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        } catch (Throwable $exception) {
            return $result->failed('Connexion impossible : '.$exception->getMessage());
        }

        $result->shortSummary($elapsedMs.' ms');

        $thresholdMs = (int) config('baobab.health.database.latency_warning_ms', 200);

        if ($elapsedMs > $thresholdMs) {
            return $result->warning("Latence anormale ({$elapsedMs} ms, seuil {$thresholdMs} ms).");
        }

        return $result->ok();
    }
}
