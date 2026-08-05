@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <div
            x-data="studioEntities({
                entities: @js($values['entities']),
                needChoices: @js($typesNeedingChoices),
            })"
        >
            <x-baobab::form method="POST" action="{{ $formAction }}">
                <input type="hidden" name="entities" x-bind:value="payload">

                <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.studio.entities.intro') }}</p>

                <template x-if="entities.length === 0">
                    <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-6 text-center text-sm text-muted">
                        {{ __('baobab::admin.studio.entities.empty') }}
                    </p>
                </template>

                <div class="space-y-3">
                    <template x-for="(entity, entityIndex) in entities" :key="entityIndex">
                        <div class="overflow-hidden rounded-lg border border-border bg-surface">
                            {{-- En-tête replié : la signature de l'entité, artefact réel en mono --}}
                            <div class="flex items-center gap-3 px-4 py-3">
                                <button
                                    type="button"
                                    class="flex flex-1 items-center gap-2 text-left"
                                    x-on:click="toggle(entityIndex)"
                                    x-bind:aria-expanded="open === entityIndex ? 'true' : 'false'"
                                >
                                    <span
                                        class="flex shrink-0 text-muted transition-transform"
                                        x-bind:class="open === entityIndex ? 'rotate-90' : ''"
                                    >
                                        <x-baobab::icon name="bi-chevron-right" class="h-4 w-4" />
                                    </span>

                                    <span class="font-mono text-sm font-medium text-foreground" x-text="entity.key || '—'"></span>
                                    <x-baobab::icon name="bi-arrow-right-short" class="h-4 w-4 shrink-0 text-muted" />
                                    <span class="font-mono text-sm text-muted" x-text="entity.table || '—'"></span>

                                    <span class="ml-2 text-xs text-muted" x-text="summary(entity)"></span>
                                </button>

                                <button
                                    type="button"
                                    class="shrink-0 rounded-md p-1 text-danger hover:bg-surface-subtle"
                                    x-on:click="removeEntity(entityIndex)"
                                    aria-label="{{ __('baobab::admin.studio.entities.remove_entity') }}"
                                    title="{{ __('baobab::admin.studio.entities.remove_entity') }}"
                                >
                                    <x-baobab::icon name="bi-trash" class="h-4 w-4" />
                                </button>
                            </div>

                            {{-- Corps déplié --}}
                            <div class="border-t border-border p-4" x-show="open === entityIndex" x-cloak>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-foreground" x-bind:for="'entity-key-' + entityIndex">
                                            {{ __('baobab::admin.studio.entities.entity_key') }}
                                        </label>
                                        <input
                                            type="text"
                                            x-bind:id="'entity-key-' + entityIndex"
                                            x-model="entity.key"
                                            x-on:input="syncTable(entity)"
                                            placeholder="Car"
                                            class="w-full rounded-md border border-border px-3 py-2 font-mono text-sm text-foreground"
                                        >
                                        <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.studio.entities.entity_key_hint') }}</p>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-foreground" x-bind:for="'entity-table-' + entityIndex">
                                            {{ __('baobab::admin.studio.entities.entity_table') }}
                                        </label>
                                        <input
                                            type="text"
                                            x-bind:id="'entity-table-' + entityIndex"
                                            x-model="entity.table"
                                            x-on:input="entity._tableTouched = true"
                                            placeholder="cars"
                                            class="w-full rounded-md border border-border px-3 py-2 font-mono text-sm text-foreground"
                                        >
                                        <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.studio.entities.entity_table_hint') }}</p>
                                    </div>
                                </div>

                                <fieldset class="mt-4">
                                    <legend class="mb-2 text-sm font-medium text-foreground">{{ __('baobab::admin.studio.entities.options') }}</legend>
                                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                                        <label class="flex items-center gap-2 text-sm text-foreground">
                                            <input type="checkbox" x-model="entity.options.timestamps" class="rounded border-border">
                                            {{ __('baobab::admin.studio.entities.option_timestamps') }}
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-foreground">
                                            <input type="checkbox" x-model="entity.options.soft_deletes" class="rounded border-border">
                                            {{ __('baobab::admin.studio.entities.option_soft_deletes') }}
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-foreground">
                                            <input type="checkbox" x-model="entity.options.uuid" class="rounded border-border">
                                            {{ __('baobab::admin.studio.entities.option_uuid') }}
                                        </label>
                                    </div>
                                </fieldset>

                                {{-- Champs --}}
                                <div class="mt-6">
                                    <h3 class="mb-2 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.studio.entities.fields') }}</h3>

                                    <template x-if="entity.fields.length === 0">
                                        <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.entities.no_fields') }}</p>
                                    </template>

                                    <div class="space-y-2">
                                        <template x-for="(field, fieldIndex) in entity.fields" :key="fieldIndex">
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
                                                    <label class="flex items-center gap-1 text-xs text-muted">
                                                        <input type="checkbox" x-model="field.unique" class="rounded border-border">
                                                        {{ __('baobab::admin.studio.entities.field_unique') }}
                                                    </label>
                                                    <label class="flex items-center gap-1 text-xs text-muted">
                                                        <input type="checkbox" x-model="field.indexed" class="rounded border-border">
                                                        {{ __('baobab::admin.studio.entities.field_indexed') }}
                                                    </label>

                                                    <button
                                                        type="button"
                                                        class="ml-auto rounded p-1 text-danger hover:bg-surface"
                                                        x-on:click="entity.fields.splice(fieldIndex, 1)"
                                                        aria-label="{{ __('baobab::admin.studio.entities.remove_field') }}"
                                                        title="{{ __('baobab::admin.studio.entities.remove_field') }}"
                                                    >
                                                        <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                                                    </button>
                                                </div>

                                                {{-- `choices` est obligatoire pour select/multiselect/radio --}}
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

                                    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="addField(entity)">
                                        {{ __('baobab::admin.studio.entities.add_field') }}
                                    </x-baobab::button>
                                </div>

                                {{-- Relations --}}
                                <div class="mt-6">
                                    <h3 class="mb-2 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.studio.entities.relations') }}</h3>

                                    <template x-if="entity.relations.length === 0">
                                        <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.entities.no_relations') }}</p>
                                    </template>

                                    <div class="space-y-2">
                                        <template x-for="(relation, relationIndex) in entity.relations" :key="relationIndex">
                                            <div class="flex flex-wrap items-center gap-2 rounded-md border border-border bg-surface-subtle p-2">
                                                <input
                                                    type="text"
                                                    x-model="relation.key"
                                                    placeholder="{{ __('baobab::admin.studio.entities.relation_key') }}"
                                                    aria-label="{{ __('baobab::admin.studio.entities.relation_key') }}"
                                                    class="w-40 rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                                                >

                                                <select
                                                    x-model="relation.type"
                                                    aria-label="{{ __('baobab::admin.studio.entities.relation_type') }}"
                                                    class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground"
                                                >
                                                    @foreach ($relationTypes as $type)
                                                        <option value="{{ $type }}">{{ $type }}</option>
                                                    @endforeach
                                                </select>

                                                <select
                                                    x-model="relation.target"
                                                    aria-label="{{ __('baobab::admin.studio.entities.relation_target') }}"
                                                    class="rounded-md border border-border bg-surface px-2 py-1 font-mono text-sm text-foreground"
                                                >
                                                    <option value="">—</option>
                                                    <optgroup label="{{ __('baobab::admin.studio.entities.target_group_entities') }}">
                                                        <template x-for="sibling in siblingTargets(entityIndex)" :key="sibling">
                                                            <option x-bind:value="sibling" x-text="sibling"></option>
                                                        </template>
                                                    </optgroup>
                                                    @if (! empty($contentTypeTargets))
                                                        <optgroup label="{{ __('baobab::admin.studio.entities.target_group_content_types') }}">
                                                            @foreach ($contentTypeTargets as $target)
                                                                <option value="content_type:{{ $target }}">content_type:{{ $target }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                    <optgroup label="{{ __('baobab::admin.studio.entities.target_group_core') }}">
                                                        @foreach ($coreTargets as $target)
                                                            <option value="core:{{ $target }}">core:{{ $target }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                </select>

                                                <select
                                                    x-model="relation.on_delete"
                                                    aria-label="{{ __('baobab::admin.studio.entities.relation_on_delete') }}"
                                                    class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground"
                                                >
                                                    @foreach ($onDeleteOptions as $option)
                                                        <option value="{{ $option }}">{{ $option }}</option>
                                                    @endforeach
                                                </select>

                                                <button
                                                    type="button"
                                                    class="ml-auto rounded p-1 text-danger hover:bg-surface"
                                                    x-on:click="entity.relations.splice(relationIndex, 1)"
                                                    aria-label="{{ __('baobab::admin.studio.entities.remove_relation') }}"
                                                    title="{{ __('baobab::admin.studio.entities.remove_relation') }}"
                                                >
                                                    <x-baobab::icon name="bi-x-lg" class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </template>
                                    </div>

                                    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="addRelation(entity)">
                                        {{ __('baobab::admin.studio.entities.add_relation') }}
                                    </x-baobab::button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <x-baobab::button type="button" variant="secondary" class="mt-3" x-on:click="addEntity()">
                    {{ __('baobab::admin.studio.entities.add_entity') }}
                </x-baobab::button>

                <div class="mt-10 flex items-center justify-between">
                    <x-baobab::button :href="route('admin.studio.step.show', [$draft, 1])" variant="ghost">
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
        function studioEntities(config) {
            return {
                needChoices: config.needChoices,
                open: 0,

                entities: config.entities.map((entity) => ({
                    ...entity,
                    // Une entité déjà enregistrée porte une table établie : ne
                    // jamais la réécrire depuis la clé au prochain caractère saisi.
                    _tableTouched: true,
                    options: {
                        timestamps: entity.options?.timestamps ?? true,
                        soft_deletes: entity.options?.soft_deletes ?? false,
                        uuid: entity.options?.uuid ?? false,
                    },
                    fields: (entity.fields ?? []).map((field) => ({
                        ...field,
                        _choicesText: (field.options?.choices ?? []).join('\n'),
                    })),
                    relations: entity.relations ?? [],
                })),

                /**
                 * Point de sérialisation unique : les clés d'interface
                 * (préfixées `_`) ne sortent jamais d'ici, et `choices`
                 * redevient un tableau.
                 */
                get payload() {
                    return JSON.stringify(this.entities.map((entity) => ({
                        key: entity.key,
                        table: entity.table,
                        options: entity.options,
                        fields: entity.fields.map((field) => ({
                            key: field.key,
                            type: field.type,
                            required: field.required,
                            unique: field.unique,
                            indexed: field.indexed,
                            options: {
                                choices: (field._choicesText ?? '')
                                    .split('\n')
                                    .map((choice) => choice.trim())
                                    .filter(Boolean),
                            },
                        })),
                        relations: entity.relations,
                    })));
                },

                needsChoices(type) {
                    return this.needChoices.includes(type);
                },

                summary(entity) {
                    return @js(__('baobab::admin.studio.entities.summary'))
                        .replace(':fields', entity.fields.length)
                        .replace(':relations', entity.relations.length);
                },

                toggle(index) {
                    this.open = this.open === index ? null : index;
                },

                addEntity() {
                    this.entities.push({
                        key: '',
                        table: '',
                        _tableTouched: false,
                        options: { timestamps: true, soft_deletes: false, uuid: false },
                        fields: [],
                        relations: [],
                    });
                    this.open = this.entities.length - 1;
                },

                removeEntity(index) {
                    this.entities.splice(index, 1);
                    this.open = null;
                },

                addField(entity) {
                    entity.fields.push({
                        key: '',
                        type: 'text',
                        required: false,
                        unique: false,
                        indexed: false,
                        _choicesText: '',
                    });
                },

                addRelation(entity) {
                    entity.relations.push({ key: '', type: 'one_to_many', target: '', on_delete: 'restrict' });
                },

                /**
                 * Cibles `entity:` proposées en direct depuis l'état courant —
                 * une entité ne peut pas se cibler elle-même.
                 */
                siblingTargets(currentIndex) {
                    return this.entities
                        .filter((entity, index) => index !== currentIndex && entity.key)
                        .map((entity) => 'entity:' + entity.key);
                },

                /**
                 * Suggestion de table tant que l'utilisateur n'a pas repris la
                 * main. Pluriel naïf assumé : la valeur affichée est celle qui
                 * sera stockée (le champ est éditable), donc aucun écart entre
                 * ce qu'on voit et ce qu'on enregistre. Le serveur ne dérive
                 * que si le champ est laissé vide.
                 */
                syncTable(entity) {
                    if (entity._tableTouched) {
                        return;
                    }

                    const snake = entity.key
                        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
                        .toLowerCase();

                    if (snake === '') {
                        entity.table = '';
                        return;
                    }

                    if (/(s|x|z|ch|sh)$/.test(snake)) {
                        entity.table = snake + 'es';
                    } else if (/[^aeiou]y$/.test(snake)) {
                        entity.table = snake.slice(0, -1) + 'ies';
                    } else {
                        entity.table = snake + 's';
                    }
                },
            };
        }
    </script>
@endonce
