<?php

declare(strict_types=1);

namespace Baobab\Search;

/**
 * Un résultat individuel (spec 11 §4.1/§4.2) — volontairement minimal pour
 * cette passe (moteur + indexation) : affiné au contact du premier vrai
 * consommateur (omnibox admin, Pass B) plutôt que devinée à l'avance.
 */
final readonly class SearchResultItem
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $excerpt = null,
    ) {}
}
