<?php

declare(strict_types=1);

namespace Baobab\Forms\Exceptions;

use RuntimeException;

final class InvalidFormBlueprintException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    private function __construct(string $message, private readonly array $errors)
    {
        parent::__construct($message);
    }

    public static function forField(string $path, string $reason): self
    {
        return new self("Blueprint de formulaire invalide — {$path}: {$reason}", [$path => [$reason]]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
