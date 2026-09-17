<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Illuminate\Support\Facades\DB;
use PDO;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Contrôle « Version MySQL » (spec 12 §7.2) — planchers spec 15 §4 (MySQL ≥
 * 8, MariaDB ≥ 10.6). Sans objet sur un autre driver (SQLite « pour
 * l'évaluation » uniquement, spec 15) : `ok`, jamais un échec hors sujet.
 *
 * `PDO::ATTR_SERVER_VERSION` sur MariaDB rapporte parfois un préfixe de
 * compatibilité MySQL 5.5 (`5.5.5-10.6.12-MariaDB`) — retiré avant de
 * comparer, sans quoi toute version MariaDB serait lue comme un MySQL 5.5.5.
 */
final class DatabaseVersionCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $driver = config('database.default');
        $connectionDriver = config("database.connections.{$driver}.driver");

        if (! in_array($connectionDriver, ['mysql', 'mariadb'], true)) {
            return $result->ok("Sans objet ({$connectionDriver}).");
        }

        try {
            $raw = (string) DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable $exception) {
            return $result->failed('Connexion impossible : '.$exception->getMessage());
        }

        $isMariaDb = str_contains($raw, 'MariaDB');
        $version = $this->extractVersion($raw);

        $minimum = $isMariaDb
            ? (string) config('baobab.health.database.mariadb_min_version', '10.6.0')
            : (string) config('baobab.health.database.mysql_min_version', '8.0.0');

        $label = $isMariaDb ? 'MariaDB' : 'MySQL';
        $result->shortSummary("{$label} {$version}");

        if ($version === null || version_compare($version, $minimum, '<')) {
            return $result->failed("{$label} {$version} détecté, {$minimum} minimum requis.");
        }

        return $result->ok();
    }

    private function extractVersion(string $raw): ?string
    {
        $raw = preg_replace('/^5\.5\.5-/', '', $raw) ?? $raw;

        return preg_match('/^(\d+\.\d+\.\d+)/', $raw, $matches) === 1 ? $matches[1] : null;
    }
}
