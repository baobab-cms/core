<?php

declare(strict_types=1);

namespace Baobab\Privacy\Exceptions;

use RuntimeException;

final class ErasureAlreadyScheduledException extends RuntimeException
{
    public static function forSubject(): self
    {
        return new self(__('baobab::privacy.erasure.already_scheduled'));
    }
}
