<?php

declare(strict_types=1);

namespace Baobab\Studio\Exceptions;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;

final class InvalidModuleBlueprintException extends RuntimeException
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

        return new self("Blueprint de module invalide — {$summary}", $errors);
    }

    public static function malformedJson(string $reason): self
    {
        return new self("Blueprint de module invalide — JSON malformé ({$reason})", []);
    }

    public static function forField(string $path, string $reason): self
    {
        return new self("Blueprint de module invalide — {$path}: {$reason}", [$path => [$reason]]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
