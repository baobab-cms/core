<?php

declare(strict_types=1);

namespace Baobab\Api\Support;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Applique filtres/tri/includes/visibilité (spec 08 §2.1) à une requête
 * Eloquent pour un Content Type donné — whitelist = colonnes structurelles
 * (id/status/published_at/created_at/updated_at) + champs `exposed_in_api`
 * du blueprint pour filter/sort, clés de `relations[]` pour include. Un
 * paramètre hors whitelist lève une `ValidationException` (422 RFC 9457),
 * jamais un 500 ni un filtre silencieusement ignoré.
 *
 * Prend des tableaux plutôt qu'une `Request` HTTP (M7 point 3, GraphQL) :
 * un seul endroit qui sait filtrer/trier/scoper un Content Type, consommé
 * par le REST (`ContentController`, qui extrait `?filter[]`/`?sort=` de la
 * requête) et par les résolveurs GraphQL (qui traduisent leurs arguments
 * dans les mêmes formes) — jamais deux implémentations du même contrat.
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
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): void
    {
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
     * @param  list<array{key: string, direction?: string}>  $sorts  Vide = tri par défaut (`-id`).
     */
    public function applySort(Builder $query, array $sorts): void
    {
        if ($sorts === []) {
            $query->orderByDesc('id');

            return;
        }

        $allowed = $this->filterableKeys();

        foreach ($sorts as $sort) {
            $key = $sort['key'];
            $direction = ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (! in_array($key, $allowed, true)) {
                throw ValidationException::withMessages([
                    'sort' => ["Le champ « {$key} » n'est pas triable."],
                ]);
            }

            $query->orderBy($key, $direction);
        }
    }

    /**
     * Applique les relations demandées et retourne celles effectivement
     * appliquées (whitelist = `relations[].key` du blueprint) — une seule
     * profondeur, pas d'inclusion imbriquée en Pass A.
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $requested
     * @return list<string>
     */
    public function applyIncludes(Builder $query, array $requested): array
    {
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
     * Visibilité d'une liste (spec 08 §2.4/§4.3) : un acteur avec `viewAny`
     * voit tout ; sinon, un type adressable à lecture publique se limite
     * aux entrées publiées ; sinon aucun accès (l'appelant décide de la
     * traduction protocole — 401/403 HTTP, erreur GraphQL...).
     *
     * @param  Builder<Model>  $query
     */
    public function scopeListVisibility(Builder $query, ?User $actor): bool
    {
        if ($actor instanceof User && $actor->can('viewAny', $this->contentType->modelClass())) {
            return true;
        }

        if ($this->contentType->is_addressable && $this->contentType->publicApiReadEnabled()) {
            $query->where('status', 'published');

            return true;
        }

        return false;
    }

    /**
     * Visibilité d'une entrée unique — même règle que `scopeListVisibility()`
     * appliquée à un modèle déjà résolu plutôt qu'à une requête.
     */
    public function canView(Model $entry, ?User $actor): bool
    {
        if (
            $this->contentType->is_addressable
            && $this->contentType->publicApiReadEnabled()
            && $entry->getAttribute('status') === 'published'
        ) {
            return true;
        }

        return $actor instanceof User && $actor->can('view', $entry);
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
