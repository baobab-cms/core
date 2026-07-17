<?php

declare(strict_types=1);

namespace Baobab\Menus\Exceptions;

use RuntimeException;

final class MenuDepthExceededException extends RuntimeException
{
    public static function forMax(int $max): self
    {
        return new self("Profondeur de menu dépassée — {$max} niveaux maximum (spec 10 §4 décision 2).");
    }
}
