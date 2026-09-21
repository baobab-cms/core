<?php

declare(strict_types=1);

namespace Baobab\Privacy\Exceptions;

use RuntimeException;

final class RequestNotCancellableException extends RuntimeException
{
    public static function forRequest(): self
    {
        return new self(__('baobab::privacy.erasure.not_cancellable'));
    }
}
