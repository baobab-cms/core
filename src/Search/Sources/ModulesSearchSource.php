<?php

declare(strict_types=1);

namespace Baobab\Search\Sources;

use Baobab\Modules\Models\Module;
use Baobab\Search\Contracts\SearchSource;
use Baobab\Search\SearchResultItem;
use Baobab\Search\SearchResults;
use Baobab\Users\Models\User;

/**
 * Source Core « modules » (spec 11 §3.2) — contexte `admin` uniquement.
 * Requête directe (`LIKE`), pas de Scout : mêmes raisons que
 * `UsersSearchSource`/`MediaSearchSource`. Permission `baobab.system.modules.manage`,
 * patron exact `routes/admin.php` (groupe `modules.`) — même garde que
 * l'écran `admin/modules` que ces résultats pointent tous vers.
 */
final class ModulesSearchSource implements SearchSource
{
    public function key(): string
    {
        return 'core.modules';
    }

    public function label(): string
    {
        return 'Modules';
    }

    /**
     * @return list<'admin'|'front'>
     */
    public function contexts(): array
    {
        return ['admin'];
    }

    public function query(string $term, ?User $actor, string $context = 'admin', array $options = []): SearchResults
    {
        if (! $actor?->can('baobab.system.modules.manage')) {
            return new SearchResults([]);
        }

        $modules = Module::query()
            ->where(fn ($query) => $query->where('title', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))
            ->limit(10)
            ->get();

        return new SearchResults(array_values($modules->map(fn (Module $module): SearchResultItem => new SearchResultItem(
            title: $module->title,
            url: route('admin.modules.index'),
            excerpt: $module->name,
            sourceKey: $this->key(),
            sourceLabel: $this->label(),
        ))->all()));
    }
}
