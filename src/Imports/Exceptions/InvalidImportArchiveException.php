<?php

declare(strict_types=1);

namespace Baobab\Imports\Exceptions;

use RuntimeException;

/**
 * Refus d'une archive d'export de contenu à l'import (spec 12 §5, cadrage
 * Pass F2, suivi n° 328). Les messages sont écrits pour la personne qui a
 * cliqué sur « Importer », pas pour un journal — patron exact
 * `InvalidModuleArchiveException`.
 */
final class InvalidImportArchiveException extends RuntimeException
{
    public static function notAZipArchive(): self
    {
        return new self("Le fichier envoyé n'est pas une archive ZIP.");
    }

    public static function unreadable(): self
    {
        return new self("L'archive est illisible ou corrompue.");
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(sprintf(
            "L'archive dépasse la taille maximale autorisée (%s Mo).",
            number_format($maxBytes / 1024 / 1024, 0, ',', ' '),
        ));
    }

    public static function manifestMissing(): self
    {
        return new self("L'archive ne contient aucun manifest.json : ce n'est pas un export Baobab.");
    }

    public static function manifestMalformed(): self
    {
        return new self("Le manifest.json de l'archive est illisible.");
    }

    public static function unsafeEntry(string $entry): self
    {
        return new self("L'archive contient un chemin non sûr et a été refusée : {$entry}");
    }

    public static function entryMissing(string $entry): self
    {
        return new self("L'archive annonce {$entry} dans son manifest, mais ce fichier est absent.");
    }

    public static function checksumMismatch(string $entry): self
    {
        return new self("Le fichier {$entry} ne correspond pas au checksum déclaré dans manifest.json : l'archive semble altérée.");
    }

    public static function blueprintMalformed(string $key): self
    {
        return new self("Le blueprint du content type {$key} est illisible.");
    }

    public static function recordMalformed(string $name): self
    {
        return new self("Un enregistrement de {$name} est illisible.");
    }

    public static function unsupportedFormatVersion(string $found, string $expected): self
    {
        return new self("Format d'export {$found} non pris en charge (attendu : {$expected}).");
    }
}
