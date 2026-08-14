<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Countable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::table :columns="…" :rows="…">` — tableau d'admin : tri par
 * colonne, actions groupées, pagination, état vide.
 *
 * Deux blocs `@php` vivaient dans la vue (suivi n° 138), et le second était le
 * plus coûteux : il recalculait le tri courant **à chaque colonne traversée**,
 * alors que la question ne se pose qu'une fois par requête. Il est ici résolu
 * à la construction, les colonnes n'en consommant plus que le résultat.
 *
 * Le composant lit la requête courante (`sort`/`direction`) — c'est déjà ce
 * que faisait la vue, et le tri d'un tableau d'admin n'a pas d'autre source.
 */
final class Table extends Component
{
    public bool $hasBulkActions;

    public bool $isEmpty;

    public bool $isPaginated;

    private string $currentSort;

    private string $currentDirection;

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  iterable<array-key, mixed>  $rows
     * @param  array<int, array<string, mixed>>  $bulkActions
     */
    public function __construct(
        public array $columns = [],
        public iterable $rows = [],
        public array $bulkActions = [],
        public string $rowKey = 'id',
    ) {
        $this->hasBulkActions = $bulkActions !== [];
        $this->isEmpty = $rows instanceof Countable ? count($rows) === 0 : $rows === [];
        $this->isPaginated = $rows instanceof Paginator;

        $sort = request('sort');
        $direction = request('direction', 'asc');

        $this->currentSort = is_string($sort) ? $sort : '';
        $this->currentDirection = $direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * L'URL qui trie sur cette colonne — en inversant le sens si elle porte
     * déjà le tri courant.
     *
     * @param  array<string, mixed>  $column
     */
    public function sortUrl(array $column): string
    {
        $key = $this->columnKey($column);
        $next = $key === $this->currentSort && $this->currentDirection === 'asc' ? 'desc' : 'asc';

        return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $next]);
    }

    /**
     * La flèche de sens, ou `null` si le tri courant ne porte pas sur cette
     * colonne. Un caractère réel, jamais une entité HTML : la vue échappe sa
     * sortie, et `&uarr;` s'y affichait littéralement — défaut corrigé avec
     * cette conversion.
     *
     * @param  array<string, mixed>  $column
     */
    public function sortArrow(array $column): ?string
    {
        if ($this->columnKey($column) !== $this->currentSort) {
            return null;
        }

        return $this->currentDirection === 'asc' ? '↑' : '↓';
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function columnKey(array $column): string
    {
        $key = $column['key'] ?? '';

        return is_string($key) ? $key : '';
    }

    public function render(): View
    {
        return view('baobab::components.table');
    }
}
