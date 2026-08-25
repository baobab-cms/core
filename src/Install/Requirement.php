<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Une exigence constatée (spec 15 §4, étape 1).
 *
 * `remedy` n'est pas décoratif : la spec impose que **chaque échec bloquant
 * soit expliqué avec son remède**. Un prérequis qui dit seulement « non » à
 * quelqu'un qui n'a ni shell ni root le laisse devant un mur.
 */
final readonly class Requirement
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $satisfied,
        public bool $blocking = true,
        public ?string $detail = null,
        public ?string $remedy = null,
    ) {}

    public static function blocking(string $key, string $label, bool $satisfied, ?string $detail = null, ?string $remedy = null): self
    {
        return new self($key, $label, $satisfied, true, $detail, $remedy);
    }

    public static function advisory(string $key, string $label, bool $satisfied, ?string $detail = null, ?string $remedy = null): self
    {
        return new self($key, $label, $satisfied, false, $detail, $remedy);
    }

    /** Un échec qui empêche l'installation de continuer. */
    public function isBlockingFailure(): bool
    {
        return $this->blocking && ! $this->satisfied;
    }
}
