@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <x-baobab::form method="POST" action="{{ $formAction }}">
            <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.studio.menus.intro') }}</p>

            @if ($menusTooDeep)
                {{--
                    Le blueprint porte des sous-menus de niveau 3+, que le
                    constructeur ne sait pas afficher. On montre le bloc tel
                    quel plutôt que de l'écraser : le handler ignore alors
                    toute soumission de cette étape.
                --}}
                <p class="mb-4 rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-sm text-foreground">
                    {{ __('baobab::admin.studio.menus.too_deep') }}
                </p>

                <pre class="mb-4 overflow-x-auto rounded-lg border border-border bg-surface p-4 font-mono text-sm leading-normal text-foreground">{{ json_encode($values['menus'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            @else
                <div
                    x-data="studioMenus({
                        items: @js($values['menus']),
                    })"
                >
                    <input type="hidden" name="menus" x-bind:value="payload">

                    <template x-if="items.length === 0">
                        <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-6 text-center text-sm text-muted">
                            {{ __('baobab::admin.studio.menus.empty') }}
                        </p>
                    </template>

                    <div class="space-y-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="rounded-lg border border-border bg-surface p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <x-baobab::icon name="bi-list" class="h-4 w-4 shrink-0 text-muted" />
                                    <span class="flex-1 text-sm font-medium text-foreground" x-text="item.label || '—'"></span>

                                    <button
                                        type="button"
                                        class="rounded-md p-1 text-danger hover:bg-surface-subtle"
                                        x-on:click="items.splice(index, 1)"
                                        aria-label="{{ __('baobab::admin.studio.menus.remove_item') }}"
                                        title="{{ __('baobab::admin.studio.menus.remove_item') }}"
                                    >
                                        <x-baobab::icon name="bi-trash" class="h-4 w-4" />
                                    </button>
                                </div>

                                @include('baobab::admin.studio.steps.partials.menu-item-fields', [
                                    'model' => 'item',
                                    'routeChoices' => $menuRouteChoices,
                                    'permissionChoices' => $menuPermissionChoices,
                                ])

                                {{--
                                    Sans route ni sous-entrée, le Core filtre l'entrée
                                    (`SidebarBuilder::toSidebarItem()`) : elle ne s'afficherait
                                    jamais. Signalé ici avant l'enregistrement, refusé par la
                                    cross-validation ensuite.
                                --}}
                                <p
                                    class="mt-2 text-xs text-warning"
                                    x-show="!item.route && item.children.length === 0"
                                    x-cloak
                                >
                                    {{ __('baobab::admin.studio.menus.dead_entry') }}
                                </p>

                                {{-- Sous-entrées : un seul niveau, cf. docblock du handler --}}
                                <div class="mt-4 border-t border-border pt-3">
                                    <h4 class="mb-2 text-xs font-medium text-muted">{{ __('baobab::admin.studio.menus.children') }}</h4>

                                    <div class="space-y-2">
                                        <template x-for="(child, childIndex) in item.children" :key="childIndex">
                                            <div class="rounded-md border border-border bg-surface-subtle p-3">
                                                <div class="mb-2 flex items-center gap-2">
                                                    <span class="flex-1 text-sm text-foreground" x-text="child.label || '—'"></span>

                                                    <button
                                                        type="button"
                                                        class="rounded p-1 text-danger hover:bg-surface"
                                                        x-on:click="item.children.splice(childIndex, 1)"
                                                        aria-label="{{ __('baobab::admin.studio.menus.remove_child') }}"
                                                        title="{{ __('baobab::admin.studio.menus.remove_child') }}"
                                                    >
                                                        <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                                                    </button>
                                                </div>

                                                @include('baobab::admin.studio.steps.partials.menu-item-fields', [
                                                    'model' => 'child',
                                                    'routeChoices' => $menuRouteChoices,
                                                    'permissionChoices' => $menuPermissionChoices,
                                                ])
                                            </div>
                                        </template>
                                    </div>

                                    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="addChild(item)">
                                        {{ __('baobab::admin.studio.menus.add_child') }}
                                    </x-baobab::button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <x-baobab::button type="button" variant="secondary" class="mt-3" x-on:click="addItem()">
                        {{ __('baobab::admin.studio.menus.add_item') }}
                    </x-baobab::button>

                    @include('baobab::admin.studio.steps.partials.icon-catalogue')
                </div>
            @endif

            <div class="mt-10 flex items-center justify-between">
                <x-baobab::button :href="route('admin.studio.step.show', [$draft, 5])" variant="ghost">
                    {{ __('baobab::admin.studio.previous') }}
                </x-baobab::button>

                <x-baobab::button type="submit" variant="primary">
                    {{ $isLastImplementedStep ? __('baobab::admin.studio.save') : __('baobab::admin.studio.save_and_continue') }}
                </x-baobab::button>
            </div>
        </x-baobab::form>
    </x-baobab::page>
@endsection

@once
    <script>
        function studioMenus(config) {
            const blank = () => ({ label: '', icon: '', route: '', permission: '', order: '' });

            return {
                items: config.items.map((item) => ({
                    ...blank(),
                    ...item,
                    children: (item.children ?? []).map((child) => ({ ...blank(), ...child })),
                })),

                get payload() {
                    return JSON.stringify(this.items.map((item) => ({
                        label: item.label,
                        icon: item.icon,
                        route: item.route,
                        permission: item.permission,
                        order: item.order,
                        children: (item.children ?? []).map((child) => ({
                            label: child.label,
                            icon: child.icon,
                            route: child.route,
                            permission: child.permission,
                            order: child.order,
                        })),
                    })));
                },

                addItem() {
                    this.items.push({ ...blank(), children: [] });
                },

                addChild(item) {
                    item.children.push(blank());
                },
            };
        }
    </script>
@endonce
