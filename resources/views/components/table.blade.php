@if ($isEmpty)
    <x-baobab::empty-state />
@else
    {{--
        Le formulaire des actions groupées est déclaré à côté de la table, pas autour — les
        colonnes peuvent rendre leurs propres formulaires par ligne (bouton impersonation,
        suppression), et imbriquer un <form> dans un autre est invalide en HTML : le navigateur
        abandonne le formulaire imbriqué et le clic soumet le formulaire englobant à la place. Les
        cases à cocher et boutons restent visuellement dans/près de la table mais s'y rattachent
        via l'attribut form="", sans imbrication réelle.
    --}}
    @if ($hasBulkActions)
        <form id="table-bulk-actions" method="POST" action="{{ $bulkActions[0]['route'] ?? '' }}">
            @csrf
        </form>
    @endif

    <div {{ $attributes->class(['overflow-x-auto rounded-lg border border-border']) }}>
        @if ($hasBulkActions)
            <div class="flex items-center gap-2 border-b border-border bg-surface-subtle px-3 py-2">
                @foreach ($bulkActions as $bulkAction)
                    <button
                        type="submit"
                        form="table-bulk-actions"
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
                                <a href="{{ $sortUrl($column) }}" class="hover:text-foreground">
                                    {{ $column['label'] }}
                                    @if ($sortArrow($column))
                                        <span aria-hidden="true">{{ $sortArrow($column) }}</span>
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
                                <input type="checkbox" name="ids[]" value="{{ data_get($row, $rowKey) }}" form="table-bulk-actions">
                            </td>
                        @endif

                        @foreach ($columns as $column)
                            <td class="px-3 py-2 text-foreground">
                                @if (isset($column['render']) && ($column['raw'] ?? false))
                                    {!! ($column['render'])($row) !!}
                                @elseif (isset($column['render']))
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
    </div>

    @if ($isPaginated)
        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    @endif
@endif
