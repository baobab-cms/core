<?php

declare(strict_types=1);

namespace Baobab\Api\Support;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Applique `?filter[]`/`?sort=`/`?include=` (spec 08 §2.1) à une requête
 * Eloquent pour un Content Type donné — whitelist = colonnes structurelles
 * (id/status/published_at/created_at/updated_at) + champs `exposed_in_api`
 * du blueprint pour filter/sort, clés de `relations[]` pour include. Un
 * paramètre hors whitelist lève une `ValidationException` (422 RFC 9457),
 * jamais un 500 ni un filtre silencieusement ignoré.
 */
final class ContentQueryBuilder
{
    /**
     * @var list<string>
     */
    private const STRUCTURAL_KEYS = ['id', 'status', 'published_at', 'created_at', 'updated_at'];

    public function __construct(private readonly ContentType $contentType) {}

    /**
     * @param  Builder<Model>  $query
     */
    public function applyFilters(Builder $query, Request $request): void
    {
        /** @var array<string, mixed> $filters */
        $filters = (array) $request->query('filter', []);
        $allowed = $this->filterableKeys();

        foreach ($filters as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    "filter.{$key}" => ["Le champ « {$key} » n'est pas filtrable."],
                ]);
            }

            if (is_array($value)) {
                foreach ($value as $operator => $operand) {
                    $this->applyOperator($query, $key, (string) $operator, $operand);
                }

                continue;
            }

            $this->applyOperator($query, $key, 'eq', $value);
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyOperator(Builder $query, string $key, string $operator, mixed $operand): void
    {
        match ($operator) {
            'eq' => $query->where($key, $operand),
            'neq' => $query->where($key, '!=', $operand),
            'lt' => $query->where($key, '<', $operand),
            'lte' => $query->where($key, '<=', $operand),
            'gt' => $query->where($key, '>', $operand),
            'gte' => $query->where($key, '>=', $operand),
            'in' => $query->whereIn($key, is_array($operand) ? $operand : explode(',', (string) $operand)),
            'like' => $query->where($key, 'like', '%'.$operand.'%'),
            default => throw ValidationException::withMessages([
                "filter.{$key}" => ["Opérateur « {$operator} » inconnu."],
            ]),
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', '');

        if ($sort === '') {
            $query->orderByDesc('id');

            return;
        }

        $allowed = $this->filterableKeys();

        foreach (explode(',', $sort) as $token) {
            $desc = str_starts_with($token, '-');
            $key = ltrim($token, '-');

            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'sort' => ["Le champ « {$key} » n'est pas triable."],
                ]);
            }

            $query->orderBy($key, $desc ? 'desc' : 'asc');
        }
    }

    /**
     * Applique `?include=` et retourne les clés de relation effectivement
     * demandées (whitelist = `relations[].key` du blueprint) — une seule
     * profondeur, pas de `?include=brand.owner` en Pass A.
     *
     * @param  Builder<Model>  $query
     * @return list<string>
     */
    public function applyIncludes(Builder $query, Request $request): array
    {
        $requested = array_values(array_filter(explode(',', (string) $request->query('include', ''))));

        /** @var list<string> $allowed */
        $allowed = array_column((array) ($this->contentType->blueprint['relations'] ?? []), 'key');

        foreach ($requested as $key) {
            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'include' => ["La relation « {$key} » n'existe pas sur ce type."],
                ]);
            }
        }

        if ($requested !== []) {
            $query->with($requested);
        }

        return $requested;
    }

    /**
     * @return list<string>
     */
    private function filterableKeys(): array
    {
        return [
            ...self::STRUCTURAL_KEYS,
            ...array_column($this->contentType->apiExposedFields(), 'key'),
        ];
    }
}
