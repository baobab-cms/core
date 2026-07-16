<?php

declare(strict_types=1);

namespace Baobab\Themes\Validation;

/**
 * Une violation du contrat de thème (spec 03 §10.3) — `blocking` distingue
 * les motifs interdits (rejet) des avertissements (publication marketplace
 * soumise à revue, sans marketplace à ce jour : informatif seulement).
 */
final readonly class ThemeViolation
{
    public function __construct(
        public string $file,
        public ?int $line,
        public string $message,
        public bool $blocking,
    ) {}
}
