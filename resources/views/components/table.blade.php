@props([
    'columns' => [],
    'rows' => [],
    'bulkActions' => [],
    'rowKey' => 'id',
])

@php
    $hasBulkActions = ! empty($bulkActions);
    $isEmpty = $rows instanceof \Countable ? count($rows) === 0 : empty($rows);
@endphp

@if ($isEmpty)
    <x-baobab::empty-state />
@else
    <div {{ $attributes->class(['overflow-x-auto rounded-lg border border-border']) }}>
        <form method="POST" action="{{ $hasBulkActions ? ($bulkActions[0]['route'] ?? '') : '' }}">
            @csrf

            @if ($hasBulkActions)
                <div class="flex items-center gap-2 border-b border-border bg-surface-subtle px-3 py-2">
                    @foreach ($bulkActions as $bulkAction)
                        <button
                            type="submit"
                            formaction="{{ $bulkAction['route'] }}"
                            class="rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-foreground hover:bg-surface-subtle"
                        >
                            {{ $bulkAction['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif

            <table class="w-full text-left text-sm">
                <thead class="bg-surface-subtle text-xs uppercase text-muted">
                    <tr>
                        @if ($hasBulkActions)
                            <th class="w-10 px-3 py-2">
                                <input
                                    type="checkbox"
                                    x-data
                                    x-on:change="$el.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(i => i.checked = $el.checked)"
                                    aria-label="{{ __('baobab::admin.components.select_all') }}"
                                >
                            </th>
                        @endif

                        @foreach ($columns as $column)
                            <th class="px-3 py-2 font-medium">
                                @if ($column['sortable'] ?? false)
                                    @php
                                        $currentSort = request('sort');
                                        $currentDirection = request('direction', 'asc');
                                        $nextDirection = $currentSort === $column['key'] && $currentDirection === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <a
                                        href="{{ request()->fullUrlWithQuery(['sort' => $column['key'], 'direction' => $nextDirection]) }}"
                                        class="hover:text-foreground"
                                    >
                                        {{ $column['label'] }}
                                        @if ($currentSort === $column['key'])
                                            <span aria-hidden="true">{{ $currentDirection === 'asc' ? '&uarr;' : '&darr;' }}</span>
                                        @endif
                                    </a>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    @foreach ($rows as $row)
                        <tr>
                            @if ($hasBulkActions)
                                <td class="px-3 py-2">
                                    <input type="checkbox" name="ids[]" value="{{ data_get($row, $rowKey) }}">
                                </td>
                            @endif

                            @foreach ($columns as $column)
                                <td class="px-3 py-2 text-foreground">
                                    @if (isset($column['render']))
                                        {{ ($column['render'])($row) }}
                                    @else
                                        {{ data_get($row, $column['key']) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </form>
    </div>

    @if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    @endif
@endif
