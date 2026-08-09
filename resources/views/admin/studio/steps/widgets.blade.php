@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <div
            x-data="studioWidgets({
                widgets: @js($values['widgets']),
                needChoices: @js($typesNeedingChoices),
            })"
        >
            <x-baobab::form method="POST" action="{{ $formAction }}">
                <input type="hidden" name="widgets" x-bind:value="payload">

                <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.widgets.intro') }}</p>
                <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.studio.widgets.zones_hint') }}</p>

                <template x-if="widgets.length === 0">
                    <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-6 text-center text-sm text-muted">
                        {{ __('baobab::admin.studio.widgets.empty') }}
                    </p>
                </template>

                <div class="space-y-3">
                    <template x-for="(widget, index) in widgets" :key="index">
                        <div class="rounded-lg border border-border bg-surface p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="flex-1 font-mono text-sm font-medium text-foreground" x-text="widget.key || '—'"></span>

                                <button
                                    type="button"
                                    class="rounded-md p-1 text-danger hover:bg-surface-subtle"
                                    x-on:click="widgets.splice(index, 1)"
                                    aria-label="{{ __('baobab::admin.studio.widgets.remove_widget') }}"
                                    title="{{ __('baobab::admin.studio.widgets.remove_widget') }}"
                                >
                                    <x-baobab::icon name="bi-trash" class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="grid gap-2 sm:grid-cols-2">
                                <div>
                                    <input
                                        type="text"
                                        x-model="widget.key"
                                        x-on:input="syncClassName(widget)"
                                        placeholder="fleet.latest-cars"
                                        aria-label="{{ __('baobab::admin.studio.widgets.key') }}"
                                        class="w-full rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                    >
                                    <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.studio.widgets.key_hint') }}</p>
                                </div>

                                <div>
                                    <input
                                        type="text"
                                        x-model="widget.label"
                                        placeholder="{{ __('baobab::admin.studio.widgets.label') }}"
                                        aria-label="{{ __('baobab::admin.studio.widgets.label') }}"
                                        class="w-full rounded-md border border-border px-2 py-1 text-sm text-foreground"
                                    >
                                </div>

                                <div>
                                    <input
                                        type="text"
                                        x-model="widget.class_name"
                                        x-on:input="widget._classTouched = true"
                                        placeholder="LatestCarsWidget"
                                        aria-label="{{ __('baobab::admin.studio.widgets.class_name') }}"
                                        class="w-full rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                    >
                                    {{-- Le fichier réellement écrit, composé à la frappe --}}
                                    <p class="mt-1 font-mono text-xs text-muted" x-text="classFile(widget)"></p>
                                </div>

                                <div>
                                    <input
                                        type="number"
                                        x-model="widget.cache_ttl"
                                        placeholder="{{ __('baobab::admin.studio.widgets.cache_ttl') }}"
                                        aria-label="{{ __('baobab::admin.studio.widgets.cache_ttl') }}"
                                        class="w-full rounded-md border border-border px-2 py-1 text-sm text-foreground"
                                    >
                                    <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.studio.widgets.cache_ttl_hint') }}</p>
                                </div>
                            </div>

                            {{-- Champs de réglages : même forme que les champs d'entité (étape 2) --}}
                            <div class="mt-4 border-t border-border pt-3">
                                <h4 class="mb-2 text-xs font-medium text-muted">{{ __('baobab::admin.studio.widgets.settings_fields') }}</h4>

                                <template x-if="widget.settings_fields.length === 0">
                                    <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.widgets.no_settings') }}</p>
                                </template>

                                <div class="space-y-2">
                                    <template x-for="(field, fieldIndex) in widget.settings_fields" :key="fieldIndex">
                                        <div class="rounded-md border border-border bg-surface-subtle p-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <input
                                                    type="text"
                                                    x-model="field.key"
                                                    placeholder="{{ __('baobab::admin.studio.entities.field_key') }}"
                                                    aria-label="{{ __('baobab::admin.studio.entities.field_key') }}"
                                                    class="w-40 rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                                >

                                                <select
                                                    x-model="field.type"
                                                    aria-label="{{ __('baobab::admin.studio.entities.field_type') }}"
                                                    class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground"
                                                >
                                                    @foreach ($fieldTypes as $type)
                                                        <option value="{{ $type }}">{{ $type }}</option>
                                                    @endforeach
                                                </select>

                                                <label class="flex items-center gap-1 text-xs text-muted">
                                                    <input type="checkbox" x-model="field.required" class="rounded border-border">
                                                    {{ __('baobab::admin.studio.entities.field_required') }}
                                                </label>

                                                <button
                                                    type="button"
                                                    class="ml-auto rounded p-1 text-danger hover:bg-surface"
                                                    x-on:click="widget.settings_fields.splice(fieldIndex, 1)"
                                                    aria-label="{{ __('baobab::admin.studio.entities.remove_field') }}"
                                                    title="{{ __('baobab::admin.studio.entities.remove_field') }}"
                                                >
                                                    <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                                                </button>
                                            </div>

                                            <div class="mt-2" x-show="needsChoices(field.type)" x-cloak>
                                                <label class="mb-1 block text-xs text-muted">{{ __('baobab::admin.studio.entities.field_choices') }}</label>
                                                <textarea
                                                    x-model="field._choicesText"
                                                    rows="3"
                                                    class="w-full rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                                ></textarea>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="addField(widget)">
                                    {{ __('baobab::admin.studio.widgets.add_setting') }}
                                </x-baobab::button>
                            </div>
                        </div>
                    </template>
                </div>

                <x-baobab::button type="button" variant="secondary" class="mt-3" x-on:click="addWidget()">
                    {{ __('baobab::admin.studio.widgets.add_widget') }}
                </x-baobab::button>

                <div class="mt-10 flex items-center justify-between">
                    <x-baobab::button :href="route('admin.studio.step.show', [$draft, 6])" variant="ghost">
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
        function studioWidgets(config) {
            return {
                needChoices: config.needChoices,

                widgets: config.widgets.map((widget) => ({
                    cache_ttl: '',
                    ...widget,
                    // Un widget déjà enregistré porte un nom de classe établi :
                    // ne jamais le réécrire depuis la clé au prochain caractère.
                    _classTouched: true,
                    settings_fields: (widget.settings_fields ?? []).map((field) => ({
                        required: false,
                        ...field,
                        _choicesText: (field.options?.choices ?? []).join('\n'),
                    })),
                })),

                get payload() {
                    return JSON.stringify(this.widgets.map((widget) => ({
                        key: widget.key,
                        label: widget.label,
                        class_name: widget.class_name,
                        cache_ttl: widget.cache_ttl,
                        settings_fields: widget.settings_fields.map((field) => ({
                            key: field.key,
                            type: field.type,
                            required: field.required,
                            options: {
                                choices: (field._choicesText ?? '')
                                    .split('\n')
                                    .map((choice) => choice.trim())
                                    .filter(Boolean),
                            },
                        })),
                    })));
                },

                needsChoices(type) {
                    return this.needChoices.includes(type);
                },

                /**
                 * Chemin réellement écrit par WidgetGenerator. Le namespace
                 * n'est pas connu du blueprint : on montre donc le chemin de
                 * fichier, qui lui l'est.
                 */
                classFile(widget) {
                    return widget.class_name ? 'src/Widgets/' + widget.class_name + '.php' : '—';
                },

                /**
                 * Nom de classe suggéré depuis la clé (`fleet.latest-cars` →
                 * `LatestCars`) tant que l'utilisateur n'a pas repris la main.
                 */
                syncClassName(widget) {
                    if (widget._classTouched) {
                        return;
                    }

                    const tail = (widget.key || '').split('.').pop() ?? '';

                    widget.class_name = tail
                        .split(/[-_]/)
                        .filter(Boolean)
                        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
                        .join('');
                },

                addWidget() {
                    this.widgets.push({
                        key: '',
                        label: '',
                        class_name: '',
                        cache_ttl: '',
                        _classTouched: false,
                        settings_fields: [],
                    });
                },

                addField(widget) {
                    widget.settings_fields.push({
                        key: '',
                        type: 'text',
                        required: false,
                        _choicesText: '',
                    });
                },
            };
        }
    </script>
@endonce
