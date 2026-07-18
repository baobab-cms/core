<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Baobab\Api\Actions\ResolveApiContentType;
use Baobab\Api\Http\Resources\ContentEntryResource;
use Baobab\Api\Support\ContentQueryBuilder;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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
        $queryBuilder->applyFilters($query, $request);
        $queryBuilder->applySort($query, $request);
        $includes = $queryBuilder->applyIncludes($query, $request);

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
        $includes = (new ContentQueryBuilder($contentType))->applyIncludes($query, $request);

        $model = $query->find($entry);
        abort_if($model === null, 404);

        $this->authorizeShow($contentType, $model);

        $fields = $this->requestedFields($request);

        return response()->json([
            'data' => (new ContentEntryResource($model, $contentType, $fields, $includes))->resolve($request),
        ]);
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

        if ($actor instanceof User && $actor->can('viewAny', $contentType->modelClass())) {
            return;
        }

        if ($contentType->is_addressable && $contentType->publicApiReadEnabled()) {
            $query->where('status', 'published');

            return;
        }

        abort_unless($actor instanceof User, 401);
        abort(403);
    }

    private function authorizeShow(ContentType $contentType, Model $entry): void
    {
        if (
            $contentType->is_addressable
            && $contentType->publicApiReadEnabled()
            && $entry->getAttribute('status') === 'published'
        ) {
            return;
        }

        $actor = $this->actor();

        abort_unless($actor instanceof User, 401);
        abort_unless($actor->can('view', $entry), 403);
    }

    private function actor(): ?User
    {
        /** @var User|null $user */
        $user = Auth::guard('baobab')->user();

        return $user;
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
