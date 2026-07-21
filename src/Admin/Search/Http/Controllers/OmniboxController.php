<?php

declare(strict_types=1);

namespace Baobab\Admin\Search\Http\Controllers;

use Baobab\Search\Actions\RunSearch;
use Baobab\Search\SearchResultItem;
use Baobab\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Endpoint JSON de l'omnibox admin (spec 11 §4.1, `Cmd+K`) — adaptateur
 * mince au-dessus de `RunSearch` (contexte `admin`, acteur courant). Routes
 * nommées `admin.omnibox.*`, délibérément hors du préfixe `admin.search.`
 * bloqué pendant une impersonation (`ImpersonationGuard`) : chercher reste
 * permis en impersonation, seul l'écran système ne l'est pas — et les
 * résultats sont de toute façon bornés par les policies de l'acteur
 * impersoné, appliquées à la requête par chaque source.
 */
final class OmniboxController
{
    public function search(Request $request, RunSearch $runSearch): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        $groups = collect($runSearch($term, 'admin', $actor))
            ->map(fn (array $group, string $key): array => [
                'source' => $key,
                'label' => $group['label'],
                'items' => array_map(
                    fn (SearchResultItem $item): array => $item->toArray(),
                    $group['results']->items,
                ),
            ])
            ->values();

        return response()->json($groups);
    }
}
