<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use Baobab\Users\Models\User;
use RuntimeException;

final class ContentLockedException extends RuntimeException
{
    public static function heldBy(User $holder): self
    {
        return new self("Ce contenu est en cours d'édition par {$holder->name}.");
    }
}
