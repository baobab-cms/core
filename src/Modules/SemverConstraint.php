<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Composer\Semver\Semver;

/**
 * Fine couche autour de composer/semver — isole le reste du Core du détail de
 * la librairie de comparaison de versions (découplage fort, spec 01 §0.3).
 */
final class SemverConstraint
{
    public static function satisfiedBy(string $constraint, string $version): bool
    {
        return Semver::satisfies($version, $constraint);
    }
}
