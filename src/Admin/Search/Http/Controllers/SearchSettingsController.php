<?php

declare(strict_types=1);

namespace Baobab\Admin\Search\Http\Controllers;

use Baobab\Search\Actions\ReindexSearch;
use Baobab\Search\SearchRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Écran « Recherche » (spec 11 §4.3) — URL `admin/search` (à plat, patron
 * des 7 écrans système existants ; la spec écrit `admin/system/search`,
 * écart d'URL validé avec l'utilisateur, la permission reste
 * `baobab.system.search.manage`). Interdit pendant une impersonation
 * (`ImpersonationGuard::BLOCKED_ROUTE_PREFIXES`, préfixe `admin.search.` —
 * l'omnibox `admin.omnibox.*` reste accessible, elle). Statut Meilisearch
 * (spec : « le cas échéant ») sans objet tant que le driver n'est pas
 * configurable depuis l'admin — la ligne « driver actif » suffit.
 */
final class SearchSettingsController
{
    public function __construct(
        private readonly SearchRegistry $registry,
        private readonly ReindexSearch $reindex,
    ) {}

    public function index(): View
    {
        $sources = [];

        foreach (array_keys($this->registry->all()) as $key) {
            $source = $this->registry->resolve($key);

            $sources[] = [
                'key' => $source->key(),
                'label' => $source->label(),
                'contexts' => implode(', ', $source->contexts()),
            ];
        }

        $contentTypes = [];

        foreach ($this->reindex->searchableContentTypes() as $contentType) {
            $modelClass = $contentType->modelClass();

            $contentTypes[] = [
                'key' => $contentType->key,
                'searchable_fields' => implode(', ', array_column($contentType->searchableFields(), 'key')),
                'total' => $modelClass::query()->count(),
            ];
        }

        return view('baobab::admin.search.index', [
            'driver' => (string) config('scout.driver'),
            'sources' => $sources,
            'contentTypes' => $contentTypes,
        ]);
    }

    public function reindex(): RedirectResponse
    {
        $count = ($this->reindex)();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.search.reindexed', ['count' => $count]),
        ]);

        return redirect()->route('admin.search.index');
    }
}
