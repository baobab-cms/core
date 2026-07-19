<?php

declare(strict_types=1);

namespace Baobab\Api\GraphQL;

use Baobab\Api\Actions\ResolveApiContentType;
use Baobab\Api\Support\ApiActor;
use Baobab\Api\Support\ContentQueryBuilder;
use Baobab\ContentTypes\Models\ContentType;
use GraphQL\Error\Error;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Logique de lecture GraphQL partagée par tous les Content Types (M7 point
 * 3, spec 08 §3) — un seul service Core, délégué par une classe résolveur
 * fine générée par Content Type (`{Key}Resolver`, patron Policy/Provider),
 * jamais dupliqué. Réutilise telles quelles `ResolveApiContentType` et
 * `ContentQueryBuilder` (filtres/tri/visibilité) : le `CarFilter` GraphQL et
 * le `?filter[x][y]=z` REST retombent sur exactement le même code
 * d'application des filtres — parité garantie, pas juste visée.
 */
final class ContentTypeGraphqlResolver
{
    public function __construct(private readonly string $slug) {}

    /**
     * Résolveur `builder` de `@paginate` (M7 point 3, spec 08 §3.2) — reçoit
     * les mêmes arguments qu'un résolveur de champ Lighthouse standard,
     * retourne un `Builder` non exécuté : la pagination (`first`/`page`,
     * enveloppe `{Key}Paginator`) reste entièrement gérée par Lighthouse.
     *
     * @param  array<string, mixed>  $args
     * @return Builder<Model>
     */
    public function paginateQuery(mixed $root, array $args): Builder
    {
        $contentType = $this->resolveOrFail();

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();
        $query = $modelClass::query();

        $actor = app(ApiActor::class)();
        $queryBuilder = new ContentQueryBuilder($contentType);

        if (! $queryBuilder->scopeListVisibility($query, $actor)) {
            throw $this->visibilityException($actor);
        }

        /** @var array<string, mixed> $filter */
        $filter = (array) ($args['filter'] ?? []);
        $queryBuilder->applyFilters($query, $filter);

        /** @var list<array{field: string, direction?: string}> $orderBy */
        $orderBy = (array) ($args['orderBy'] ?? []);
        $queryBuilder->applySort($query, $this->translateOrderBy($orderBy));

        return $query;
    }

    /**
     * Résolveur `@field` de la lecture unité — même règle de visibilité que
     * REST (`ContentController::show()`/`authorizeShow()`), traduite en
     * erreurs GraphQL plutôt qu'en 401/403 HTTP.
     *
     * @param  array{id?: int|string, slug?: string}  $args
     */
    public function find(mixed $root, array $args): ?Model
    {
        $contentType = $this->resolveOrFail();

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        $model = match (true) {
            isset($args['id']) => $modelClass::query()->find($args['id']),
            isset($args['slug']) && $contentType->is_addressable => $modelClass::query()
                ->where('slug', $args['slug'])->first(),
            default => null,
        };

        if (! $model instanceof Model) {
            return null;
        }

        $actor = app(ApiActor::class)();

        if (! (new ContentQueryBuilder($contentType))->canView($model, $actor)) {
            throw $this->visibilityException($actor);
        }

        return $model;
    }

    private function resolveOrFail(): ContentType
    {
        $contentType = app(ResolveApiContentType::class)($this->slug);

        if ($contentType === null) {
            throw new Error("Content type « {$this->slug} » indisponible.");
        }

        return $contentType;
    }

    private function visibilityException(?object $actor): AuthenticationException|AuthorizationException
    {
        return $actor === null
            ? new AuthenticationException
            : new AuthorizationException;
    }

    /**
     * @param  list<array{field: string, direction?: string}>  $orderBy
     * @return list<array{key: string, direction?: string}>
     */
    private function translateOrderBy(array $orderBy): array
    {
        return array_map(
            static fn (array $order): array => ['key' => $order['field'], 'direction' => $order['direction'] ?? 'asc'],
            $orderBy,
        );
    }
}
