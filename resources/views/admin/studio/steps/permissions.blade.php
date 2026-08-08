@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <div
            x-data="studioPermissions({
                autoCrud: @js($values['auto_crud']),
                custom: @js($values['custom']),
                entities: @js($permissionEntities),
            })"
        >
            <x-baobab::form method="POST" action="{{ $formAction }}">
                <input type="hidden" name="permissions" x-bind:value="payload">

                <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.studio.permissions.intro') }}</p>

                @if (empty($permissionEntities))
                    <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-6 text-center text-sm text-muted">
                        {{ __('baobab::admin.studio.permissions.no_entities') }}
                    </p>
                @endif

                {{-- CRUD automatique --}}
                <x-baobab::card :header="__('baobab::admin.studio.permissions.auto_crud_title')">
                    <label class="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" x-model="autoCrud" class="rounded border-border">
                        {{ __('baobab::admin.studio.permissions.auto_crud_label') }}
                    </label>

                    <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.studio.permissions.auto_crud_hint') }}</p>

                    @if (! empty($permissionEntities))
                        <div class="mt-4 space-y-3" x-bind:class="autoCrud ? '' : 'opacity-40'">
                            @foreach ($permissionEntities as $entity)
                                <div>
                                    <p class="mb-1 text-xs font-medium text-muted">{{ $entity['key'] }}</p>
                                    <ul class="flex flex-wrap gap-1.5">
                                        @foreach ($entity['crud'] as $permission)
                                            <li class="rounded-md bg-surface-subtle px-2 py-1 font-mono text-sm text-foreground">
                                                {{ $permission['key'] }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-baobab::card>

                {{-- Permissions personnalisées --}}
                <x-baobab::card :header="__('baobab::admin.studio.permissions.custom_title')" class="mt-4">
                    <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.studio.permissions.custom_intro') }}</p>

                    <template x-if="custom.length === 0">
                        <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.studio.permissions.no_custom') }}</p>
                    </template>

                    <div class="space-y-2">
                        <template x-for="(permission, index) in custom" :key="index">
                            <div class="rounded-md border border-border bg-surface-subtle p-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <select
                                        x-model="permission.entity"
                                        aria-label="{{ __('baobab::admin.studio.permissions.custom_entity') }}"
                                        class="rounded-md border border-border bg-surface px-2 py-1 font-mono text-sm text-foreground"
                                    >
                                        <option value="">—</option>
                                        @foreach ($permissionEntities as $entity)
                                            <option value="{{ $entity['key'] }}">{{ $entity['key'] }}</option>
                                        @endforeach
                                    </select>

                                    <input
                                        type="text"
                                        x-model="permission.key"
                                        placeholder="{{ __('baobab::admin.studio.permissions.custom_key') }}"
                                        aria-label="{{ __('baobab::admin.studio.permissions.custom_key') }}"
                                        class="w-40 rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                    >

                                    <input
                                        type="text"
                                        x-model="permission.label"
                                        placeholder="{{ __('baobab::admin.studio.permissions.custom_label') }}"
                                        aria-label="{{ __('baobab::admin.studio.permissions.custom_label') }}"
                                        class="w-56 rounded-md border border-border px-2 py-1 text-sm text-foreground"
                                    >

                                    <button
                                        type="button"
                                        class="ml-auto rounded p-1 text-danger hover:bg-surface"
                                        x-on:click="custom.splice(index, 1)"
                                        aria-label="{{ __('baobab::admin.studio.permissions.remove_custom') }}"
                                        title="{{ __('baobab::admin.studio.permissions.remove_custom') }}"
                                    >
                                        <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                                    </button>
                                </div>

                                {{-- L'artefact réel, composé à la frappe --}}
                                <p class="mt-2 font-mono text-sm text-muted" x-text="fullKey(permission)"></p>
                            </div>
                        </template>
                    </div>

                    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="addCustom()">
                        {{ __('baobab::admin.studio.permissions.add_custom') }}
                    </x-baobab::button>
                </x-baobab::card>

                <div class="mt-10 flex items-center justify-between">
                    <x-baobab::button :href="route('admin.studio.step.show', [$draft, 2])" variant="ghost">
                        {{ __('baobab::admin.studio.previous') }}
                    </x-baobab::button>

                    <x-baobab::button type="submit" variant="primary">
                        {{ $isLastImplementedStep ? __('baobab::admin.studio.save') : __('baobab::admin.studio.save_and_continue') }}
                    </x-baobab::button>
                </div>
            </x-baobab::form>
        </div>
    </x-baobab::page>
@endsection

@once
    <script>
        function studioPermissions(config) {
            return {
                autoCrud: config.autoCrud,
                custom: config.custom.map((permission) => ({ ...permission })),
                entities: config.entities,

                get payload() {
                    return JSON.stringify({
                        auto_crud: this.autoCrud,
                        custom: this.custom.map((permission) => ({
                            entity: permission.entity,
                            key: permission.key,
                            label: permission.label,
                        })),
                    });
                },

                /**
                 * Chaîne complète telle qu'elle sera écrite dans le manifeste :
                 * le préfixe vient du serveur (même dérivation que le
                 * générateur), seul le suffixe est composé ici.
                 */
                fullKey(permission) {
                    const entity = this.entities.find((candidate) => candidate.key === permission.entity);

                    if (!entity) {
                        return '—';
                    }

                    return entity.prefix + '.' + (permission.key || '…');
                },

                addCustom() {
                    this.custom.push({
                        entity: this.entities.length === 1 ? this.entities[0].key : '',
                        key: '',
                        label: '',
                    });
                },
            };
        }
    </script>
@endonce
