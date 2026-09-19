<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * Le rapport consolidé d'un effacement (spec 16 §4.3) : fournisseur par
 * fournisseur, plus la référence pseudonyme du sujet.
 */
final readonly class ErasureResult
{
    /**
     * @param  array<string, EraseReport>  $reports
     * @param  list<string>  $unsupported  fournisseurs concernés mais sans effacement automatique
     */
    public function __construct(
        public string $reference,
        public array $reports,
        public array $unsupported = [],
    ) {}

    /**
     * @return array<string, array{outcome: string, count: int, note: string}>
     */
    public function reportsToArray(): array
    {
        return array_map(static fn (EraseReport $report): array => $report->toArray(), $this->reports);
    }
}
