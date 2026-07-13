<?php

declare(strict_types=1);

namespace Baobab\Media\Exceptions;

use RuntimeException;

final class ExternalMediaException extends RuntimeException
{
    public static function unsupportedUrl(string $url): self
    {
        return new self("URL non prise en charge : « {$url} » — aucun fournisseur oEmbed connu ne correspond.");
    }

    public static function resolutionFailed(string $url): self
    {
        return new self("Impossible de résoudre « {$url} » auprès du fournisseur oEmbed.");
    }

    public static function thumbnailUnavailable(string $url): self
    {
        return new self("Le fournisseur n'a pas fourni de vignette exploitable pour « {$url} ».");
    }
}
