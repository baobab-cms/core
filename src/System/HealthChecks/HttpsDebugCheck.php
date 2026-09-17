<?php

declare(strict_types=1);

namespace Baobab\System\HealthChecks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Contrôle « HTTPS / debug » (spec 12 §7.2) — une seule ligne pour les deux
 * conditions, conforme au tableau des 9 contrôles v1 (jamais deux checks
 * `spatie/laravel-health` natifs séparés, qui porteraient le total à 10).
 * Sans objet hors production : un environnement de dev tourne
 * légitimement en HTTP et `APP_DEBUG=true`.
 */
final class HttpsDebugCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        $environment = (string) config('app.env');

        if ($environment !== 'production') {
            return $result->ok("Sans objet hors production ({$environment}).");
        }

        $problems = [];

        if (config('app.debug') === true) {
            $problems[] = 'APP_DEBUG actif';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = 'HTTPS non actif';
        }

        if ($problems !== []) {
            return $result->failed(implode(', ', $problems).'.');
        }

        return $result->ok();
    }
}
