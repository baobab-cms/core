<?php

declare(strict_types=1);

use Baobab\Search\Actions\RunSearch;
use Baobab\Search\SearchResults;

if (! function_exists('baobab_search')) {
    /**
     * Helper de thème (spec 11 §4.2) — présentation pure : le thème affiche,
     * il ne construit jamais de requête (la visibilité « publiés seulement »
     * est appliquée par les sources elles-mêmes, contexte `front`). Premier
     * helper fonction du package (tous les autres points de contact thème
     * sont des composants Blade) : une page de résultats `search.blade.php`
     * doit contrôler son propre markup — le thème a besoin des données, pas
     * d'un HTML pré-rendu.
     *
     * @param  array<string, mixed>  $options  `type` (clé de Content Type), `filters` (par champ)
     * @return array<string, array{label: string, results: SearchResults}>
     */
    function baobab_search(string $term, array $options = []): array
    {
        return app(RunSearch::class)($term, 'front', null, $options);
    }
}
