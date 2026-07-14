<?php

declare(strict_types=1);

namespace Baobab\Mail\Exceptions;

use RuntimeException;

final class InvalidMailTemplateException extends RuntimeException
{
    public static function missingDefaults(string $key, string $path): self
    {
        return new self("Template d'e-mail « {$key} » invalide — fichier de défaut introuvable ({$path}).");
    }

    public static function missingRequiredVariable(string $key, string $variable): self
    {
        return new self("Template d'e-mail « {$key} » invalide — la variable requise « {$variable} » n'apparaît pas dans le sujet/corps par défaut.");
    }
}
