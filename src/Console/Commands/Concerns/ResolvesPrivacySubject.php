<?php

declare(strict_types=1);

namespace Baobab\Console\Commands\Concerns;

use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\SubjectResolver;

/**
 * Le sujet d'une commande RGPD : un identifiant de compte existant ou une
 * adresse e-mail (spec 16 §7).
 */
trait ResolvesPrivacySubject
{
    private function resolveSubject(string $raw): ?Subject
    {
        return (new SubjectResolver)->resolve($raw);
    }
}
