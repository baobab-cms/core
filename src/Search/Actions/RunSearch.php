<?php

declare(strict_types=1);

namespace Baobab\Search\Actions;

use Baobab\Facades\Hook;
use Baobab\Search\SearchRegistry;
use Baobab\Search\SearchResults;
use Baobab\Users\Models\User;

/**
 * Interroge toutes les sources d'un contexte (spec 11 §4) — LE cœur unique
 * de l'interrogation, règle de revue n° 1 (API-first) : l'omnibox admin, le
 * helper de thème `baobab_search()` et `GET /api/v1/search` sont trois
 * adaptateurs minces au-dessus de cette Action, jamais trois chemins de
 * code. Filtre `baobab.search.results` appliqué par source (spec 11 §4.4) :
 * un module post-traite les résultats d'une source, contexte et acteur
 * fournis.
 */
final class RunSearch
{
    public function __construct(private readonly SearchRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, array{label: string, results: SearchResults}>
     */
    public function __invoke(string $term, string $context, ?User $actor, array $options = []): array
    {
        $groups = [];

        foreach (array_keys($this->registry->all()) as $key) {
            $source = $this->registry->resolve($key);

            if (! in_array($context, $source->contexts(), true)) {
                continue;
            }

            $results = $source->query($term, $actor, $context, $options);

            /** @var SearchResults $results */
            $results = Hook::filter('baobab.search.results', $results, $source->key(), $context, $actor);

            if ($results->items === []) {
                continue;
            }

            $groups[$source->key()] = ['label' => $source->label(), 'results' => $results];
        }

        return $groups;
    }
}
