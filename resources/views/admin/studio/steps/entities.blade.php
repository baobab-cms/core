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

                                {{--
                                    Champs et relations : l'éditeur partagé avec le
                                    formulaire de Content Type (n° 163). Ici la
                                    collection est celle d'une entité parmi N, et les
                                    cibles sont préfixées — les deux seules choses qui
                                    distinguent cet appel de celui du contenu.
                                --}}
                                <div class="mt-6">
                                    <x-baobab::blueprint.field-rows
                                        collection="entity.fields"
                                        add-expression="addField(entity)"
                                        :field-types="$fieldTypes"
                                    />
                                </div>

                                <div class="mt-6">
                                    <x-baobab::blueprint.relation-rows
                                        collection="entity.relations"
                                        add-expression="addRelation(entity)"
                                        siblings="siblingTargets(entityIndex)"
                                        content-type-prefix="content_type:"
                                        core-prefix="core:"
                                        :relation-types="$relationTypes"
                                        :on-delete-options="$onDeleteOptions"
                                        :core-targets="$coreTargets"
                                        :content-type-targets="$contentTypeTargets"
                                    />
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
