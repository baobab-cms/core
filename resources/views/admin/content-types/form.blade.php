@extends('baobab::layouts.admin')

@section('title', $contentType === null
    ? __('baobab::admin.content_types.new')
    : __('baobab::admin.content_types.edit_title', ['key' => $contentType->key]))

@section('content')
    <x-baobab::page
        :title="$contentType === null
            ? __('baobab::admin.content_types.new')
            : __('baobab::admin.content_types.edit_title', ['key' => $contentType->key])"
        :subtitle="$contentType?->table_name"
        :breadcrumbs="[[__('baobab::admin.content_types.title'), route('admin.content-types.index')]]"
    >
        @error('blueprint')
            <div class="mb-4 rounded-md border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700">
                {{ $message }}
            </div>
        @enderror

        @error('destructive')
            <div class="mb-4 rounded-md border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700">
                {{ $message }}
                <label class="mt-2 flex items-center gap-2 font-medium">
                    <input type="checkbox" name="confirm_destructive" value="1" form="content-type-form" class="rounded border-border">
                    {{ __('baobab::admin.content_types.confirm_destructive') }}
                </label>
            </div>
        @enderror

        <div
            x-data="contentTypeBuilder({
                fields: @js($values['fields']),
                relations: @js($values['relations']),
                needChoices: @js($typesNeedingChoices),
            })"
        >
            <x-baobab::form id="content-type-form" method="{{ $contentType === null ? 'POST' : 'PUT' }}" :action="$formAction">
                <input type="hidden" name="fields" x-bind:value="fieldsPayload">
                <input type="hidden" name="relations" x-bind:value="relationsPayload">

                {{-- Identité --}}
                <section class="rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.content_types.section_identity') }}</h2>
                    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.content_types.section_identity_hint') }}</p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="key" class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('baobab::admin.content_types.key') }}
                            </label>
                            <input
                                type="text"
                                id="key"
                                name="key"
                                value="{{ old('key', $values['key']) }}"
                                placeholder="Car"
                                @disabled($contentType !== null)
                                class="w-full rounded-md border border-border px-3 py-2 font-mono text-sm text-foreground disabled:bg-surface-subtle disabled:text-muted"
                            >
                            <p class="mt-1 text-xs text-muted">
                                {{ $contentType === null
                                    ? __('baobab::admin.content_types.key_hint')
                                    : __('baobab::admin.content_types.key_immutable') }}
                            </p>
                            @error('key')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="label_singular" class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('baobab::admin.content_types.label_singular') }}
                            </label>
                            <input
                                type="text"
                                id="label_singular"
                                name="label_singular"
                                value="{{ old('label_singular', $values['label_singular']) }}"
                                placeholder="Voiture"
                                class="w-full rounded-md border border-border px-3 py-2 text-sm text-foreground"
                            >
                            @error('label_singular')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="label_plural" class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('baobab::admin.content_types.label_plural') }}
                            </label>
                            <input
                                type="text"
                                id="label_plural"
                                name="label_plural"
                                value="{{ old('label_plural', $values['label_plural']) }}"
                                placeholder="Voitures"
                                class="w-full rounded-md border border-border px-3 py-2 text-sm text-foreground"
                            >
                            @error('label_plural')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" name="is_addressable" value="1" x-model="isAddressable" class="rounded border-border">
                            {{ __('baobab::admin.content_types.is_addressable') }}
                        </label>
                        <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.content_types.is_addressable_hint') }}</p>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="isAddressable" x-cloak>
                        <div>
                            <label for="url_prefix" class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('baobab::admin.content_types.url_prefix') }}
                            </label>
                            <input
                                type="text"
                                id="url_prefix"
                                name="url_prefix"
                                value="{{ old('url_prefix', $values['url_prefix']) }}"
                                placeholder="voitures"
                                class="w-full rounded-md border border-border px-3 py-2 font-mono text-sm text-foreground"
                            >
                            <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.content_types.url_prefix_hint') }}</p>
                            @error('url_prefix')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="title_field" class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('baobab::admin.content_types.title_field') }}
                            </label>
                            {{--
                                Alimenté depuis les champs saisis à l'instant, pas
                                depuis une liste figée au chargement : le champ qui
                                servira de titre vient d'être créé juste au-dessus.
                            --}}
                            <select
                                id="title_field"
                                name="title_field"
                                x-model="titleField"
                                class="w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-sm text-foreground"
                            >
                                <option value="">—</option>
                                <template x-for="candidate in titleCandidates" :key="candidate">
                                    <option x-bind:value="candidate" x-text="candidate"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.content_types.title_field_hint') }}</p>
                            @error('title_field')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- Champs : l'éditeur partagé avec l'étape 2 du Studio (n° 163) --}}
                <section class="mt-4 rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.content_types.section_fields') }}</h2>
                    <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.content_types.section_fields_hint') }}</p>

                    <x-baobab::blueprint.field-rows
                        collection="fields"
                        add-expression="addField()"
                        :field-types="$fieldTypes"
                    />
                </section>

                {{-- Relations --}}
                <section class="mt-4 rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.content_types.section_relations') }}</h2>

                    @if ($contentType === null)
                        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.content_types.section_relations_hint') }}</p>
                    @else
                        {{--
                            Asymétrie assumée et annoncée (suivi n° 158) :
                            `EvolveContentType` ne fait évoluer que les champs. Le
                            dire ici vaut mieux que de laisser modifier une
                            relation qui ne prendra jamais effet en base.
                        --}}
                        <div class="mb-4 rounded-md border border-info-200 bg-info-50 px-3 py-2 text-sm text-info-700">
                            {{ __('baobab::admin.content_types.relations_frozen') }}
                        </div>
                    @endif

                    <div @if ($contentType !== null) class="pointer-events-none opacity-60" aria-disabled="true" @endif>
                        <x-baobab::blueprint.relation-rows
                            collection="relations"
                            add-expression="addRelation()"
                            :relation-types="$relationTypes"
                            :on-delete-options="$onDeleteOptions"
                            :core-targets="$coreTargets"
                            :content-type-targets="$contentTypeTargets"
                        />
                    </div>
                </section>

                {{-- Options éditoriales & API --}}
                <section class="mt-4 rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-4 font-display text-base font-medium text-foreground">{{ __('baobab::admin.content_types.section_options') }}</h2>

                    <div class="flex flex-wrap gap-x-6 gap-y-3">
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" name="workflow" value="1" @checked(old('workflow', $values['workflow'])) class="rounded border-border">
                            {{ __('baobab::admin.content_types.workflow') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" name="unpublish_at" value="1" @checked(old('unpublish_at', $values['unpublish_at'])) class="rounded border-border">
                            {{ __('baobab::admin.content_types.unpublish_at') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" name="api_enabled" value="1" @checked(old('api_enabled', $values['api_enabled'])) class="rounded border-border">
                            {{ __('baobab::admin.content_types.api_enabled') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" name="public_api_read" value="1" @checked(old('public_api_read', $values['public_api_read'])) class="rounded border-border">
                            {{ __('baobab::admin.content_types.public_api_read') }}
                        </label>
                    </div>

                    <div class="mt-4 max-w-xs">
                        <label for="revisions_limit" class="mb-1 block text-sm font-medium text-foreground">
                            {{ __('baobab::admin.content_types.revisions_limit') }}
                        </label>
                        <input
                            type="number"
                            id="revisions_limit"
                            name="revisions_limit"
                            min="0"
                            value="{{ old('revisions_limit', $values['revisions_limit']) }}"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm text-foreground"
                        >
                        <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.content_types.revisions_limit_hint') }}</p>
                        @error('revisions_limit')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                </section>

                <div class="mt-6 flex items-center justify-between">
                    <x-baobab::button :href="route('admin.content-types.index')" variant="ghost">
                        {{ __('baobab::admin.content_types.cancel') }}
                    </x-baobab::button>

                    <x-baobab::button type="submit" variant="primary">
                        {{ $contentType === null
                            ? __('baobab::admin.content_types.build')
                            : __('baobab::admin.content_types.save') }}
                    </x-baobab::button>
                </div>
            </x-baobab::form>
        </div>
    </x-baobab::page>
@endsection

@once
    <script>
        function contentTypeBuilder(config) {
            return {
                needChoices: config.needChoices,
                isAddressable: @js((bool) old('is_addressable', $values['is_addressable'])),
                titleField: @js(old('title_field', $values['title_field'])),

                fields: config.fields.map((field) => ({
                    ...field,
                    required: field.required ?? false,
                    unique: field.unique ?? false,
                    indexed: field.indexed ?? false,
                    searchable: field.searchable ?? false,
                    weight: field.weight ?? 1,
                    _choicesText: (field.options?.choices ?? []).join('\n'),
                })),

                relations: config.relations.map((relation) => ({ ...relation })),

                /**
                 * Points de sérialisation uniques, patron `studioEntities` : les
                 * clés d'interface (préfixées `_`) ne sortent jamais d'ici, et
                 * `choices` redevient un tableau.
                 */
                get fieldsPayload() {
                    return JSON.stringify(this.fields.map((field) => ({
                        key: field.key,
                        type: field.type,
                        required: field.required,
                        unique: field.unique,
                        indexed: field.indexed,
                        searchable: field.searchable,
                        weight: field.weight,
                        options: {
                            choices: (field._choicesText ?? '')
                                .split('\n')
                                .map((choice) => choice.trim())
                                .filter(Boolean),
                        },
                    })));
                },

                get relationsPayload() {
                    return JSON.stringify(this.relations);
                },

                /**
                 * Le slug se dérive d'un champ textuel : seuls ceux-là peuvent
                 * porter le titre (spec 02 §4.2), et la liste suit la saisie en
                 * cours plutôt qu'un état figé au chargement.
                 */
                get titleCandidates() {
                    return this.fields
                        .filter((field) => ['text', 'textarea', 'richtext'].includes(field.type) && field.key)
                        .map((field) => field.key);
                },

                needsChoices(type) {
                    return this.needChoices.includes(type);
                },

                addField() {
                    this.fields.push({
                        key: '',
                        type: 'text',
                        required: false,
                        unique: false,
                        indexed: false,
                        searchable: false,
                        weight: 1,
                        _choicesText: '',
                    });
                },

                addRelation() {
                    this.relations.push({ key: '', type: 'one_to_many', target: '', on_delete: 'restrict' });
                },
            };
        }
    </script>
@endonce
