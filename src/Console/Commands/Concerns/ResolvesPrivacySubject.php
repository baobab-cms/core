<?php

declare(strict_types=1);

namespace Baobab\Console\Commands\Concerns;

use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;

/**
 * Le sujet d'une commande RGPD : un identifiant de compte existant ou une
 * adresse e-mail (spec 16 §7).
 */
trait ResolvesPrivacySubject
{
    private function resolveSubject(string $raw): ?Subject
    {
        if (str_contains($raw, '@')) {
            return Subject::forEmail($raw);
        }

        $user = ctype_digit($raw) ? User::query()->find((int) $raw) : null;

        return $user === null ? null : Subject::forUser($user);
    }
}
