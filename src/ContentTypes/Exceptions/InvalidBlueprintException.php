<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Exceptions;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;

final class InvalidBlueprintException extends RuntimeException
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

        return new self("Blueprint de Content Type invalide — {$summary}", $errors);
    }

    public static function malformedJson(string $reason): self
    {
        return new self("Blueprint de Content Type invalide — JSON malformé ({$reason})", []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
