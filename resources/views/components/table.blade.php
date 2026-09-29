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

    {{--
        `checkedCount` (suivi n° 379 décision 1) : une action groupée peut se
        déclarer `confirm` (route ET action irréversibles, ex. suppression) —
        elle s'ouvre alors dans <x-baobab::confirm> au lieu de soumettre
        directement, avec le nombre de lignes cochées annoncé avant de
        confirmer (§9 : « le titre dit le nombre et l'objet »). Porté par le
        conteneur de la table plutôt qu'un `x-data` local à la case « tout
        sélectionner » (qui l'isolait dans son propre scope, hors d'atteinte
        des boutons d'action groupée).
    --}}
    <div {{ $attributes->class(['overflow-x-auto rounded-lg border border-border']) }} x-data="{ checkedCount: 0 }">
        @if ($hasBulkActions)
            <div class="flex items-center gap-2 border-b border-border bg-surface-subtle px-3 py-2">
                @foreach ($bulkActions as $bulkAction)
                    @if (isset($bulkAction['confirm']))
                        <x-baobab::button
                            type="button"
                            size="sm"
                            x-on:click="checkedCount > 0 && $dispatch('open-modal', 'bulk-action-confirm-{{ $loop->index }}')"
                        >
                            {{ $bulkAction['label'] }}
                        </x-baobab::button>

                        <x-baobab::confirm name="bulk-action-confirm-{{ $loop->index }}" :title="$bulkAction['confirm']['title'] ?? $bulkAction['label']">
                            <x-slot:description>
                                @if (! empty($bulkAction['confirm']['description']))
                                    <p>{{ $bulkAction['confirm']['description'] }}</p>
                                @endif
                                <p class="mt-2">
                                    {{ __('baobab::admin.components.bulk_confirm_count_prefix') }}
                                    <span class="font-mono" x-text="checkedCount"></span>
                                </p>
                            </x-slot:description>

                            <x-baobab::button type="submit" form="table-bulk-actions" formaction="{{ $bulkAction['route'] }}" variant="danger" class="w-full justify-center">
                                {{ $bulkAction['label'] }}
                            </x-baobab::button>
                        </x-baobab::confirm>
                    @else
                        <button
                            type="submit"
                            form="table-bulk-actions"
                            formaction="{{ $bulkAction['route'] }}"
                            class="rounded-md border border-border bg-surface px-2 py-1 text-xs font-medium text-foreground hover:bg-surface-subtle"
                        >
                            {{ $bulkAction['label'] }}
                        </button>
                    @endif
                @endforeach
            </div>
        @endif

        <table class="w-full text-left text-sm">
            <thead class="bg-surface-subtle text-sm text-muted">
                <tr>
                    @if ($hasBulkActions)
                        <th class="w-10 px-3 py-2">
                            <input
                                type="checkbox"
                                x-on:change="$el.closest('table').querySelectorAll('input[name=\'ids[]\']').forEach(i => i.checked = $el.checked); checkedCount = $el.closest('table').querySelectorAll('input[name=\'ids[]\']:checked').length"
                                aria-label="{{ __('baobab::admin.components.select_all') }}"
                            >
                        </th>
                    @endif

                    @foreach ($columns as $column)
                        <th class="{{ $columnHeaderClasses($column) }}">
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
                    <tr class="hover:bg-surface transition-colors duration-[120ms]">
                        @if ($hasBulkActions)
                            <td class="px-3 py-3">
                                <input
                                    type="checkbox"
                                    name="ids[]"
                                    value="{{ data_get($row, $rowKey) }}"
                                    form="table-bulk-actions"
                                    x-on:change="checkedCount = $el.closest('table').querySelectorAll('input[name=\'ids[]\']:checked').length"
                                >
                            </td>
                        @endif

                        @foreach ($columns as $column)
                            <td class="{{ $columnCellClasses($column) }}">
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
        <div class="mt-4 flex items-center justify-between gap-4">
            @if ($resultsSummary())
                <p class="text-sm text-muted">{{ $resultsSummary() }}</p>
            @endif

            {{ $rows->links() }}
        </div>
    @endif
@endif
