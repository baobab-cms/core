<?php

declare(strict_types=1);

namespace Baobab\Branding\Exceptions;

use RuntimeException;

/**
 * Patron exact `Baobab\Media\Exceptions\InvalidMediaUploadException`.
 */
final class InvalidFontUploadException extends RuntimeException
{
    public static function invalidMagicBytes(): self
    {
        return new self('Fichier invalide : la signature binaire ne correspond pas au format woff2 (« wOF2 » attendu en tête de fichier).');
    }

    public static function tooLarge(int $sizeInBytes, int $maxInBytes): self
    {
        return new self("Fichier de police trop volumineux ({$sizeInBytes} octets, maximum {$maxInBytes}).");
    }

    public static function licenseAttestationRequired(): self
    {
        return new self("L'attestation de droits d'utilisation web de cette police est obligatoire.");
    }
}
