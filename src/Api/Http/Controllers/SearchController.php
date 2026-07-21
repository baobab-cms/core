<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Baobab\Api\Support\ApiActor;
use Baobab\Search\Actions\RunSearch;
use Baobab\Search\SearchResultItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/search?q=` (spec 11 §4.2) — lecture seule, contexte `front`
 * exclusivement, contenus publiés seulement (garanti par les sources, jamais
 * re-vérifié ici). Adaptateur mince au-dessus de `RunSearch`, même patron
 * que l'omnibox et `baobab_search()`. Filtres simples dès la v1 : `?type=`
 * (clé de Content Type) et `?filter[champ]=` (restreints aux champs
 * `exposed_in_api` via `ContentQueryBuilder::applyFilters()`, 422 sinon —
 * mêmes règles que le REST v1). Hérite des middlewares du groupe
 * (CORS/interrupteur/throttle) et de l'enveloppe `{data, meta, links}`.
 * Facettes dynamiques différées en v2 (spec 11 §11 décision 2).
 */
final class SearchController
{
    public function index(Request $request, RunSearch $runSearch, ApiActor $actor): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return $this->envelope([], $request);
        }

        $options = array_filter([
            'type' => $request->query('type'),
            'filters' => (array) $request->query('filter', []),
        ]);

        $groups = $runSearch($term, 'front', $actor(), $options);

        $items = array_values(collect($groups)
            ->flatMap(fn (array $group): array => array_map(
                fn (SearchResultItem $item): array => $item->toArray(),
                $group['results']->items,
            ))
            ->all());

        return $this->envelope($items, $request);
    }

    /**
     * Même forme d'enveloppe que le REST v1 (`ContentController::envelope()`,
     * privée — la forme est partagée, pas le code : des `SearchResultItem`
     * ne sont pas des Resources de Content Type). Pagination par tranche sur
     * la liste agrégée (les sources bornent déjà leurs volumes).
     *
     * @param  list<array<string, string|null>>  $items
     */
    private function envelope(array $items, Request $request): JsonResponse
    {
        $perPage = min(max((int) ($request->query('per_page') ?? 25), 1), 100);
        $page = max((int) ($request->query('page') ?? 1), 1);
        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);
        $hasMore = $page * $perPage < $total;

        return response()->json([
            'data' => $slice,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                ],
            ],
            'links' => [
                'next' => $hasMore ? $request->fullUrlWithQuery(['page' => $page + 1]) : null,
                'prev' => $page > 1 ? $request->fullUrlWithQuery(['page' => $page - 1]) : null,
            ],
        ]);
    }
}
