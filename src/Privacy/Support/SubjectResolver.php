<?php

declare(strict_types=1);

namespace Baobab\Privacy\Support;

use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;

/**
 * Résout la saisie d'un opérateur (console ou écran admin) en `Subject` : une
 * adresse e-mail, ou l'identifiant d'un compte existant (spec 16 §7).
 * `Subject` reste un value object pur — la requête vit ici.
 */
final class SubjectResolver
{
    public function resolve(string $raw): ?Subject
    {
        $raw = trim($raw);

        if (str_contains($raw, '@')) {
            return Subject::forEmail($raw);
        }

        $user = ctype_digit($raw) ? User::query()->find((int) $raw) : null;

        return $user === null ? null : Subject::forUser($user);
    }
}
