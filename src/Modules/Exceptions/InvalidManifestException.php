<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;

final class InvalidManifestException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    private function __construct(string $message, private readonly array $errors)
    {
        parent::__construct($message);
    }

    public static function fromValidationError(ValidationError $error): self
    {
        $errors = (new ErrorFormatter)->format($error);

        $summary = collect($errors)
            ->map(fn (array $messages, string $path) => sprintf('%s: %s', $path !== '' ? $path : '(root)', implode(', ', $messages)))
            ->implode(' ; ');

        return new self("Manifest de module invalide — {$summary}", $errors);
    }

    public static function malformedJson(string $reason): self
    {
        return new self("Manifest de module invalide — JSON malformé ({$reason})", []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
