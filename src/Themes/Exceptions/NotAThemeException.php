<?php

declare(strict_types=1);

namespace Baobab\Themes\Exceptions;

use RuntimeException;

final class NotAThemeException extends RuntimeException
{
    public static function forModule(string $name): self
    {
        return new self("Le module « {$name} » n'est pas un thème (type déclaré différent de \"theme\").");
    }
}
