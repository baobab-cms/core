{{--
    Éditeur de champs d'un blueprint — le seul du produit (suivi n° 163).

    Consommé par les deux chemins que le M8 point 2 fusionne : l'étape 2 du
    wizard Studio, où la collection est celle d'une entité parmi N
    (`entity.fields`), et le formulaire de Content Type, où il n'y en a
    qu'une (`fields`). D'où le paramètre `collection` : une **expression
    Alpine**, pas des données — le composant s'insère dans la portée de son
    appelant plutôt que d'imposer la sienne.

    Les primitives Alpine attendues dans cette portée (`addField`,
    `needsChoices`) viennent de `baobabBlueprintEditor()`, poussé une fois par
    `<x-baobab::blueprint.editor-script>`.

    @param string $collection    Expression Alpine désignant le tableau de champs.
    @param array  $fieldTypes    Clés du catalogue (FieldRegistry), pour le select.
    @param string $addExpression Expression Alpine ajoutant un champ à cette collection.
--}}
@props([
    'collection',
    'fieldTypes',
    'addExpression',
])

<div>
    <h3 class="mb-1 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.studio.entities.fields') }}</h3>
    <p class="mb-2 text-xs text-muted">{{ __('baobab::admin.studio.entities.field_key_hint') }}</p>

    <template x-if="{{ $collection }}.length === 0">
        <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.entities.no_fields') }}</p>
    </template>

    <div class="space-y-2">
        <template x-for="(field, fieldIndex) in {{ $collection }}" :key="fieldIndex">
            <div class="rounded-md border border-border bg-surface-subtle p-2">
                <div class="flex flex-wrap items-center gap-2">
                    {{--
                        Une clé de champ devient un nom de colonne : le placeholder
                        montre la forme attendue, sans quoi on y saisit un libellé
                        humain (défaut réel signalé en vérification navigateur).
                    --}}
                    <input
                        type="text"
                        x-model="field.key"
                        placeholder="published_at"
                        aria-label="{{ __('baobab::admin.studio.entities.field_key') }}"
                        title="{{ __('baobab::admin.studio.entities.field_key_hint') }}"
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
                        x-on:click="{{ $collection }}.splice(fieldIndex, 1)"
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

    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="{{ $addExpression }}">
        {{ __('baobab::admin.studio.entities.add_field') }}
    </x-baobab::button>
</div>
