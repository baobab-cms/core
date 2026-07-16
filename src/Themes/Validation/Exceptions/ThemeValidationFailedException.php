<?php

declare(strict_types=1);

namespace Baobab\Themes\Validation\Exceptions;

use Baobab\Themes\Validation\ThemeViolation;
use RuntimeException;

final class ThemeValidationFailedException extends RuntimeException
{
    /**
     * @param  list<ThemeViolation>  $violations
     */
    private function __construct(string $message, private readonly array $violations)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<ThemeViolation>  $violations  Violations bloquantes uniquement.
     */
    public static function forViolations(string $themeName, array $violations): self
    {
        $summary = collect($violations)
            ->map(fn (ThemeViolation $v): string => $v->line !== null ? "{$v->file}:{$v->line} — {$v->message}" : "{$v->file} — {$v->message}")
            ->implode(' ; ');

        return new self("Thème « {$themeName} » invalide — {$summary}", $violations);
    }

    /**
     * @return list<ThemeViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
