<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class GeneratedFileConflictException extends RuntimeException
{
    public static function forFile(string $relativePath): self
    {
        return new self(
            "Le fichier généré « {$relativePath} » a été modifié depuis sa dernière génération — ".
            'rien n\'a été écrasé. Résolvez le conflit à la main avant de régénérer.'
        );
    }
}
