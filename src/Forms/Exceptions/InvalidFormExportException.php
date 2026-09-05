<?php

declare(strict_types=1);

namespace Baobab\Forms\Exceptions;

use RuntimeException;

final class InvalidFormExportException extends RuntimeException
{
    public static function malformedJson(): self
    {
        return new self('Export de formulaire invalide — JSON malformé.');
    }

    public static function unsupportedSource(string $source): self
    {
        return new self("Export de formulaire invalide — source « {$source} » non prise en charge (seul « admin » existe en v1).");
    }

    public static function unsupportedFormatVersion(int $version): self
    {
        return new self("Export de formulaire invalide — version de format « {$version} » non prise en charge.");
    }

    public static function missingSlug(): self
    {
        return new self('Export de formulaire invalide — slug manquant.');
    }
}
