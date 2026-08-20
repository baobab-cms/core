<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Baobab\Api\Actions\ResolveApiContentType;
use Baobab\Api\Http\Resources\ContentEntryResource;
use Baobab\Api\Support\ApiActor;
use Baobab\Api\Support\ContentQueryBuilder;
use Baobab\ContentTypes\Actions\DeleteContentEntry;
use Baobab\ContentTypes\Actions\PublishContentEntry;
use Baobab\ContentTypes\Actions\PurgeContentEntry;
use Baobab\ContentTypes\Actions\RestoreContentEntryFromTrash;
use Baobab\ContentTypes\Actions\SaveContentEntry;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Support\ContentEntryRules;
use Baobab\ContentTypes\Support\ContentTrash;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * REST v1 lecture seule sur le registre des types (spec 08 §2, M7 point 1,
 * Pass A) : `GET /api/v1/content/{type}` (liste) et
 * `GET /api/v1/content/{type}/{entry}` (unité). Même patron que l'admin
 * `Baobab\Admin\Content\Http\Controllers\ContentController` — un seul
 * contrôleur pour tous les types, résolu dynamiquement depuis le slug de
 * route — mais un contexte distinct (pas d'auth de session obligatoire, pas
 * de vues). Écriture (POST/PATCH/DELETE, transitions, revisions) hors
 * périmètre de cette passe (Pass B).
 */
final class ContentController
{
    public function index(Request $request, string $type): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        $query = $modelClass::query();

        $this->applyListVisibility($query, $contentType);

        $queryBuilder = new ContentQueryBuilder($contentType);
        $queryBuilder->applyFilters($query, $this->requestFilters($request));
        $queryBuilder->applySort($query, $this->requestSort($request));
        $includes = $queryBuilder->applyIncludes($query, $this->requestIncludes($request));

        $fields = $this->requestedFields($request);
        $perPage = min(max((int) ($request->query('per_page') ?? 25), 1), 100);

        $paginator = $request->has('cursor')
            ? $query->cursorPaginate($perPage)
            : $query->paginate($perPage)->withQueryString();

        return response()->json($this->envelope($paginator, $contentType, $fields, $includes, $request));
    }

    public function show(Request $request, string $type, int|string $entry): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        $query = $modelClass::query();
        $includes = (new ContentQueryBuilder($contentType))->applyIncludes($query, $this->requestIncludes($request));

        $model = $this->whereRouteKey($query, $contentType, $entry)->first();
        abort_if($model === null, 404);

        $this->authorizeShow($contentType, $model);

        $fields = $this->requestedFields($request);

        return response()->json([
            'data' => (new ContentEntryResource($model, $contentType, $fields, $includes))->resolve($request),
        ]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);
        $actor = $this->authorizeClass('create', $contentType);

        $rules = app(ContentEntryRules::class);
        $validated = $request->validate($rules->rules($contentType));

        if ($contentType->is_addressable && isset($validated['slug'])) {
            $validated['slug'] = $rules->uniqueSlug($contentType, (string) $validated['slug'], null);
        }

        $entry = app(SaveContentEntry::class)($contentType, $validated, $actor);

        return response()->json(
            ['data' => (new ContentEntryResource($entry, $contentType))->resolve($request)],
            201,
            // `getRouteKey()` et non `getKey()` : l'en-tête doit désigner
            // l'entrée par ce que la route accepte désormais.
            ['Location' => route('api.v1.content.show', ['type' => $type, 'entry' => $entry->getRouteKey()])],
        );
    }

    public function update(Request $request, string $type, int|string $entry): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);
        $model = $this->findEntry($contentType, $entry);
        $actor = $this->authorizeInstance('update', $model);

        $rules = app(ContentEntryRules::class);
        $validated = $request->validate($rules->rules($contentType, partial: true));

        if ($contentType->is_addressable && isset($validated['slug'])) {
            $validated['slug'] = $rules->uniqueSlug($contentType, (string) $validated['slug'], $model);
        }

        $updated = app(SaveContentEntry::class)($contentType, $validated, $actor, $model);

        return response()->json(['data' => (new ContentEntryResource($updated, $contentType))->resolve($request)]);
    }

    public function destroy(Request $request, string $type, int|string $entry): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);

        if ($request->boolean('force')) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();

            $model = $this->whereRouteKey(ContentTrash::withTrashed($modelClass), $contentType, $entry)->first();
            abort_if($model === null, 404);

            $this->authorizeInstance('forceDelete', $model);

            app(PurgeContentEntry::class)($contentType, $model);

            return response()->json(null, 204);
        }

        $model = $this->findEntry($contentType, $entry);
        $this->authorizeInstance('delete', $model);

        app(DeleteContentEntry::class)($contentType, $model);

        return response()->json(null, 204);
    }

    public function publish(Request $request, string $type, int|string $entry): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);
        $model = $this->findEntry($contentType, $entry);
        $actor = $this->authorizeInstance('publish', $model);

        $updated = app(PublishContentEntry::class)($contentType, $model, $actor);

        return response()->json(['data' => (new ContentEntryResource($updated, $contentType))->resolve($request)]);
    }

    public function restore(Request $request, string $type, int|string $entry): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        $model = $this->whereRouteKey(ContentTrash::onlyTrashed($modelClass), $contentType, $entry)->first();
        abort_if($model === null, 404);

        $this->authorizeInstance('restore', $model);

        $restored = app(RestoreContentEntryFromTrash::class)($contentType, $model);

        return response()->json(['data' => (new ContentEntryResource($restored, $contentType))->resolve($request)]);
    }

    /**
     * Historique manuel/pre_restore (spec 09 §6, même filtre que l'admin,
     * `ContentController::revisions()`) — jamais `autosave`/`working_draft`,
     * invisibles hors du contexte d'édition en cours. Décision de périmètre :
     * expose id/type/summary/author/created_at, jamais le `snapshot` brut
     * (contiendrait des champs non `exposed_in_api`) ; pas de restauration
     * de révision ni de diff via l'API en Pass B, non nommés par la spec.
     */
    public function revisions(string $type, int|string $entry): JsonResponse
    {
        $contentType = $this->resolveOrAbort($type);
        $model = $this->findEntry($contentType, $entry);
        $this->authorizeInstance('update', $model);

        $revisions = Revision::where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->whereIn('type', ['manual', 'pre_restore'])
            ->orderByDesc('id')
            ->with('author')
            ->get();

        return response()->json([
            'data' => $revisions->map(fn (Revision $revision): array => [
                'id' => $revision->id,
                'type' => $revision->type,
                'summary' => $revision->summary,
                'author' => $revision->author === null ? null : [
                    'id' => $revision->author->id,
                    'name' => $revision->author->name,
                ],
                'created_at' => $revision->created_at,
            ])->all(),
        ]);
    }

    private function findEntry(ContentType $type, int|string $id): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        $model = $this->whereRouteKey($modelClass::query(), $type, $id)->first();

        abort_if($model === null, 404);

        return $model;
    }

    /**
     * Contraint une requête sur la **clé de route** de l'entrée — `slug` pour
     * un type adressable, `uuid` sinon (spec 02 §4.2). Jamais `find()`, qui
     * interroge la clé primaire : l'entier n'est pas un identifiant public et
     * l'exposer sur les six routes `{entry}` livrerait le volume du catalogue
     * et le moyen de l'énumérer, `api_enabled` valant `true` par défaut.
     *
     * Bascule franche assumée (n° 166) : l'entier n'est plus accepté en repli.
     * Un repli laisserait l'énumération possible, c'est-à-dire exactement le
     * défaut que la règle corrige.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function whereRouteKey(Builder $query, ContentType $type, int|string $entry): Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $type->modelClass();

        return $query->where((new $modelClass)->getRouteKeyName(), $entry);
    }

    private function authorizeClass(string $ability, ContentType $type): User
    {
        $actor = $this->requireActor();

        abort_unless($actor->can($ability, $type->modelClass()), 403);

        return $actor;
    }

    private function authorizeInstance(string $ability, Model $model): User
    {
        $actor = $this->requireActor();

        abort_unless($actor->can($ability, $model), 403);

        return $actor;
    }

    private function requireActor(): User
    {
        $actor = $this->actor();

        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function resolveOrAbort(string $type): ContentType
    {
        $contentType = app(ResolveApiContentType::class)($type);

        abort_if($contentType === null, 404);

        return $contentType;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyListVisibility(Builder $query, ContentType $contentType): void
    {
        $actor = $this->actor();

        if ((new ContentQueryBuilder($contentType))->scopeListVisibility($query, $actor)) {
            return;
        }

        abort_unless($actor instanceof User, 401);
        abort(403);
    }

    private function authorizeShow(ContentType $contentType, Model $entry): void
    {
        $actor = $this->actor();

        if ((new ContentQueryBuilder($contentType))->canView($entry, $actor)) {
            return;
        }

        abort_unless($actor instanceof User, 401);
        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFilters(Request $request): array
    {
        /** @var array<string, mixed> $filters */
        $filters = (array) $request->query('filter', []);

        return $filters;
    }

    /**
     * @return list<array{key: string, direction: string}>
     */
    private function requestSort(Request $request): array
    {
        $sort = (string) $request->query('sort', '');

        if ($sort === '') {
            return [];
        }

        return array_map(
            fn (string $token): array => str_starts_with($token, '-')
                ? ['key' => ltrim($token, '-'), 'direction' => 'desc']
                : ['key' => $token, 'direction' => 'asc'],
            explode(',', $sort),
        );
    }

    /**
     * @return list<string>
     */
    private function requestIncludes(Request $request): array
    {
        return array_values(array_filter(explode(',', (string) $request->query('include', ''))));
    }

    /**
     * Guard `sanctum` (M7 point 2), pas `baobab` directement : résout la
     * même session admin qu'avant (repli « statefull » de Sanctum
     * configuré sur `baobab`, `registerSanctumGuard()`) *ou* un Bearer
     * token — une seule résolution d'acteur pour les deux, la restriction
     * aux abilities du token étant appliquée globalement par
     * `User::can()`, jamais ici. `forgetUser()` avant résolution :
     * `RequestGuard::user()` (driver `sanctum`) mémoïse l'utilisateur
     * résolu pour toute la durée de vie de l'instance de guard, jamais
     * rafraîchi tant qu'un process/conteneur survit à plusieurs requêtes
     * (Octane — même classe de bug déjà rencontrée dans ce code base, M6
     * point 2 : « un même process peut traiter plusieurs requêtes »),
     * jamais un souci en PHP-FPM classique où chaque requête repart d'un
     * conteneur neuf. `Auth::forgetGuards()` (toutes les guards) casserait
     * `actingAs()` du guard `baobab` sous-jacent, qui ne mute qu'un
     * utilisateur en mémoire sur l'instance de guard existante
     * (`GuardHelpers::setUser()`), jamais la session réelle — reconstruire
     * ce guard-là le désauthentifierait. `forgetUser()` (`GuardHelpers`,
     * absent du contrat `Guard` mais bien présent sur `RequestGuard`, le
     * driver réel derrière `sanctum`) cible donc uniquement ce guard :
     * même instance, cache vidé, la prochaine résolution relit le guard
     * `baobab` (inchangé) à travers lui.
     */
    private function actor(): ?User
    {
        return app(ApiActor::class)();
    }

    /**
     * @return list<string>
     */
    private function requestedFields(Request $request): array
    {
        $fields = (string) $request->query('fields', '');

        return array_values(array_filter(array_map('trim', explode(',', $fields))));
    }

    /**
     * @param  LengthAwarePaginator<int, Model>|CursorPaginator<int, Model>  $paginator
     * @param  list<string>  $fields
     * @param  list<string>  $includes
     * @return array<string, mixed>
     */
    private function envelope(
        LengthAwarePaginator|CursorPaginator $paginator,
        ContentType $contentType,
        array $fields,
        array $includes,
        Request $request,
    ): array {
        $data = $paginator->getCollection()
            ->map(fn (Model $entry): array => (new ContentEntryResource($entry, $contentType, $fields, $includes))->resolve($request))
            ->all();

        $pagination = $paginator instanceof LengthAwarePaginator
            ? [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ]
            : [
                'per_page' => $paginator->perPage(),
            ];

        return [
            'data' => $data,
            'meta' => ['pagination' => $pagination],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }
}
