<?php

declare(strict_types=1);

namespace Baobab\Media\Exceptions;

use RuntimeException;

final class MediaTooLargeException extends RuntimeException
{
    public static function forSize(int $sizeInBytes, int $maxInBytes): self
    {
        $sizeMb = round($sizeInBytes / 1_048_576, 1);
        $maxMb = round($maxInBytes / 1_048_576, 1);

        return new self("Fichier trop volumineux ({$sizeMb} Mo) — maximum autorisé : {$maxMb} Mo.");
    }
}
