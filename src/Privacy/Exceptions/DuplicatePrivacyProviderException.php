<?php

declare(strict_types=1);

namespace Baobab\Privacy\Exceptions;

use LogicException;

final class DuplicatePrivacyProviderException extends LogicException
{
    public static function forKey(string $key): self
    {
        return new self("Un fournisseur de données personnelles est déjà enregistré sous la clé : {$key}.");
    }
}
