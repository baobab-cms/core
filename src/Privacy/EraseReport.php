<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * Le rapport d'un fournisseur (spec 16 §4.3) : ce qu'il a fait, sur combien
 * d'enregistrements, et pourquoi — notamment pourquoi il a conservé. Ne porte
 * jamais de donnée personnelle : il finit dans l'audit comme preuve d'exécution.
 */
final readonly class EraseReport
{
    public function __construct(
        public EraseOutcome $outcome,
        public int $count,
        public string $note,
    ) {}

    /**
     * @return array{outcome: string, count: int, note: string}
     */
    public function toArray(): array
    {
        return ['outcome' => $this->outcome->value, 'count' => $this->count, 'note' => $this->note];
    }
}
