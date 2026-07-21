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
 * En contexte `front` (Pass B), l'acteur est ignoré délibérément (spec 11
 * §4.2 : « les contenus publiés seulement ») : un admin connecté qui
 * navigue le site public voit la même chose qu'un anonyme, et seuls les
 * types adressables (qui ont une page publique) sont interrogés.
 *
 * Options comprises (`GET /api/v1/search`) : `type` (clé de Content Type,
 * restreint la recherche à ce type) et `filters` (filtres par champ,
 * délégués à `ContentQueryBuilder::applyFilters()` — mêmes clés autorisées
 * que le REST v1, `ValidationException` sinon).
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
        return ['admin', 'front'];
    }

    public function query(string $term, ?User $actor, string $context = 'admin', array $options = []): SearchResults
    {
        $items = [];

        foreach ($this->searchableContentTypes($context, $options) as $contentType) {
            $items = [...$items, ...$this->searchContentType($contentType, $term, $actor, $context, $options)];
        }

        return new SearchResults($items);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<ContentType>
     */
    private function searchableContentTypes(string $context, array $options): array
    {
        return array_values(ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->when(isset($options['type']), fn ($query) => $query->where('key', (string) $options['type']))
            ->when($context === 'front', fn ($query) => $query->where('is_addressable', true))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->searchableFields() !== [])
            ->all());
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<SearchResultItem>
     */
    private function searchContentType(ContentType $contentType, string $term, ?User $actor, string $context, array $options): array
    {
        $modelClass = $contentType->modelClass();
        $visibility = new ContentQueryBuilder($contentType);
        $visibilityActor = $context === 'front' ? null : $actor;

        /** @var array<string, mixed> $filters */
        $filters = (array) ($options['filters'] ?? []);

        $entries = $modelClass::search($term)
            ->query(function ($query) use ($visibility, $visibilityActor, $filters): void {
                $visibility->scopeListVisibility($query, $visibilityActor);

                if ($filters !== []) {
                    $visibility->applyFilters($query, $filters);
                }
            })
            ->get();

        return $entries->map(fn (Model $entry): SearchResultItem => new SearchResultItem(
            title: $this->titleFor($contentType, $entry),
            url: $this->urlFor($contentType, $entry, $context),
            excerpt: (string) ($contentType->blueprint['label']['singular'] ?? $contentType->key),
            sourceKey: $this->key(),
            sourceLabel: $this->label(),
        ))->all();
    }

    private function urlFor(ContentType $contentType, Model $entry, string $context): string
    {
        if ($context === 'front') {
            return url('/'.$contentType->urlPrefix().'/'.$entry->getAttribute('slug'));
        }

        $slug = Str::kebab(Str::plural($contentType->key));

        return route('admin.content.edit', ['contentType' => $slug, 'entry' => $entry->getKey()]);
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
