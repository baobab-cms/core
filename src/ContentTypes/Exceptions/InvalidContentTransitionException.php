<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use RuntimeException;

final class InvalidContentTransitionException extends RuntimeException
{
    public static function forTransition(string $transition, string $from): self
    {
        return new self("Transition « {$transition} » impossible depuis le statut « {$from} ».");
    }
}
