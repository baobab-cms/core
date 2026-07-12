<?php

declare(strict_types=1);

namespace Baobab\Media\Exceptions;

use RuntimeException;

final class InvalidMediaUploadException extends RuntimeException
{
    public static function disallowedMimeType(string $mimeType): self
    {
        return new self("Type de fichier non autorisé : « {$mimeType} ».");
    }

    public static function malformedSvg(): self
    {
        return new self('Fichier SVG malformé, impossible à désinfecter.');
    }

    public static function svgNotPermitted(): self
    {
        return new self("L'import de SVG n'est pas autorisé pour cet utilisateur.");
    }

    public static function invalidUploadId(): self
    {
        return new self("Identifiant de session d'upload invalide.");
    }
}
