<?php

declare(strict_types=1);

namespace Baobab\Search;

/**
 * Résultat d'une `SearchSource::query()` (spec 11 §3.2) — une source, un
 * ensemble de résultats ; le groupement par source (§4.1, omnibox) reste au
 * consommateur, pas à ce DTO.
 */
final readonly class SearchResults
{
    /**
     * @param  list<SearchResultItem>  $items
     */
    public function __construct(public array $items) {}
}
