<?php

declare(strict_types=1);

namespace Baobab\Search\Sources;

use Baobab\Api\Support\ContentQueryBuilder;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Search\Contracts\SearchSource;
use Baobab\Search\SearchResultItem;
use Baobab\Search\SearchResults;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Source Core « contenus » (spec 11 §3.2) — tous les Content Types dont au
 * moins un champ est `searchable`. Contrairement à `UsersSearchSource`/
 * `MediaSearchSource`, passe par Scout (`{Model}::search()`), le driver
 * `database` restant une requête `LIKE` directe sur la table du Content
 * Type (`Laravel\Scout\Engines\DatabaseEngine` ne maintient aucun index
 * séparé) — la bascule vers Meilisearch ne change aucune ligne ici.
 *
 * Visibilité (spec 11 §4.1 : « les résultats passent par les policies… le
 * filtrage s'applique à la requête, pas après coup ») : même discipline que
 * `ContentQueryBuilder::scopeListVisibility()`, injectée via `Builder::query()`
 * *avant* l'exécution — jamais un filtrage de la collection après `get()`.
 */
final class ContentsSearchSource implements SearchSource
{
    public function key(): string
    {
        return 'core.contents';
    }

    public function label(): string
    {
        return 'Contenus';
    }

    /**
     * @return list<'admin'|'front'>
     */
    public function contexts(): array
    {
        return ['admin'];
    }

    public function query(string $term, ?User $actor): SearchResults
    {
        $items = [];

        foreach ($this->searchableContentTypes() as $contentType) {
            $items = [...$items, ...$this->searchContentType($contentType, $term, $actor)];
        }

        return new SearchResults($items);
    }

    /**
     * @return list<ContentType>
     */
    private function searchableContentTypes(): array
    {
        return array_values(ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->searchableFields() !== [])
            ->all());
    }

    /**
     * @return list<SearchResultItem>
     */
    private function searchContentType(ContentType $contentType, string $term, ?User $actor): array
    {
        $modelClass = $contentType->modelClass();
        $visibility = new ContentQueryBuilder($contentType);
        $slug = Str::kebab(Str::plural($contentType->key));

        $entries = $modelClass::search($term)
            ->query(function ($query) use ($visibility, $actor): void {
                $visibility->scopeListVisibility($query, $actor);
            })
            ->get();

        return $entries->map(fn (Model $entry): SearchResultItem => new SearchResultItem(
            title: $this->titleFor($contentType, $entry),
            url: route('admin.content.edit', ['contentType' => $slug, 'entry' => $entry->getKey()]),
        ))->all();
    }

    private function titleFor(ContentType $contentType, Model $entry): string
    {
        $titleField = $contentType->blueprint['title_field'] ?? null;

        if ($titleField !== null && $entry->getAttribute($titleField) !== null) {
            return (string) $entry->getAttribute($titleField);
        }

        return "{$contentType->key} #{$entry->getKey()}";
    }
}
