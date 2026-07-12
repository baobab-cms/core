<?php

declare(strict_types=1);

namespace Baobab\Media\Exceptions;

use RuntimeException;

final class FolderNotEmptyException extends RuntimeException
{
    public static function hasSubfolders(string $name): self
    {
        return new self("Le dossier « {$name} » contient des sous-dossiers — déplacez-les ou supprimez-les d'abord.");
    }
}
