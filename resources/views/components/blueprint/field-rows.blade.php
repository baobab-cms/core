{{--
    Éditeur de champs d'un blueprint — le seul du produit (suivi n° 163).

    Consommé par les deux chemins que le M8 point 2 fusionne : l'étape 2 du
    wizard Studio, où la collection est celle d'une entité parmi N
    (`entity.fields`), et le formulaire de Content Type, où il n'y en a
    qu'une (`fields`). D'où le paramètre `collection` : une **expression
    Alpine**, pas des données — le composant s'insère dans la portée de son
    appelant plutôt que d'imposer la sienne.

    Étendu en M8 point 6 Pass B2 pour un troisième appelant, le constructeur
    de formulaires : `extraFields` ajoute label/placeholder/aide (spec 14
    §2.2, sans objet pour un Content Type ou un module — ces libellés s'y
    dérivent de la clé) ; `showUniqueIndexed` masque `unique`/`indexed`
    (contraintes de colonne, sans objet pour un formulaire, dont les
    soumissions vivent en JSON) ; `draggable` active le réordonnancement —
    premier glisser-déposer du Core, aucun précédent à suivre (menus/widgets/
    galerie n'ont que des boutons ↑/↓). Les trois défauts préservent le
    comportement des deux appelants existants à l'identique.

    Étendu à nouveau (suivi n° 274, chantier 5) : `showSearchable` affiche
    `searchable`/`weight` (spec 11 §3.1) — le schéma de blueprint et
    `ContentsSearchSource` savaient déjà les lire, seul cet écran ne les
    exposait pas. Patron `showUniqueIndexed` à l'identique : défaut `true`
    (Content Types, Studio), le formulaire de Pass B2 l'éteint explicitement
    — un champ de soumission n'entre jamais dans l'index Scout.

    Les primitives Alpine attendues dans cette portée (`addField`,
    `needsChoices`) sont définies localement par chaque appelant — patron
    dupliqué trois fois plutôt qu'un composant partagé, contrairement à ce
    que suggérait un commentaire plus ancien de ce fichier (`editor-script`
    n'a jamais existé).

    L'état de glisser-déposer (`dragIndex`) est un état d'interface pur,
    entièrement local à ce fragment : il n'a aucune raison de fuiter dans
    l'état sérialisé de l'appelant.

    @param string  $collection       Expression Alpine désignant le tableau de champs.
    @param array   $fieldTypes       Clés du catalogue (FieldRegistry), pour le select.
    @param string  $addExpression    Expression Alpine ajoutant un champ à cette collection.
    @param bool    $extraFields      Ajoute label/placeholder/aide (spec 14 §2.2).
    @param bool    $showUniqueIndexed Affiche unique/indexed (défaut : oui, comme avant).
    @param bool    $draggable        Active le glisser-déposer pour réordonner.
    @param bool    $showSearchable   Affiche searchable/weight (spec 11 §3.1, défaut : oui).
--}}
@props([
    'collection',
    'fieldTypes',
    'addExpression',
    'extraFields' => false,
    'showUniqueIndexed' => true,
    'draggable' => false,
    'showSearchable' => true,
])

<div>
    <h3 class="mb-1 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.studio.entities.fields') }}</h3>
    <p class="mb-2 text-xs text-muted">{{ __('baobab::admin.studio.entities.field_key_hint') }}</p>

    <template x-if="{{ $collection }}.length === 0">
        <p class="mb-2 text-sm text-muted">{{ __('baobab::admin.studio.entities.no_fields') }}</p>
    </template>

    <div class="space-y-2" x-data="{ dragIndex: null }">
        <template x-for="(field, fieldIndex) in {{ $collection }}" :key="fieldIndex">
            <div
                class="rounded-md border border-border bg-surface-subtle p-2"
                @if ($draggable)
                    draggable="true"
                    x-on:dragstart="dragIndex = fieldIndex"
                    x-on:dragover.prevent
                    x-on:drop="{{ $collection }}.splice(fieldIndex, 0, {{ $collection }}.splice(dragIndex, 1)[0]); dragIndex = null"
                    x-bind:class="dragIndex === fieldIndex && 'opacity-50'"
                @endif
            >
                <div class="flex flex-wrap items-center gap-2">
                    @if ($draggable)
                        <span
                            class="cursor-grab text-muted"
                            aria-hidden="true"
                            title="{{ __('baobab::admin.studio.entities.drag_to_reorder') }}"
                        >
                            <x-baobab::icon name="bi-grip-vertical" class="h-4 w-4" />
                        </span>
                    @endif

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

                    @if ($showUniqueIndexed)
                        <label class="flex items-center gap-1 text-xs text-muted">
                            <input type="checkbox" x-model="field.unique" class="rounded border-border">
                            {{ __('baobab::admin.studio.entities.field_unique') }}
                        </label>
                        <label class="flex items-center gap-1 text-xs text-muted">
                            <input type="checkbox" x-model="field.indexed" class="rounded border-border">
                            {{ __('baobab::admin.studio.entities.field_indexed') }}
                        </label>
                    @endif

                    @if ($showSearchable)
                        <label class="flex items-center gap-1 text-xs text-muted">
                            <input type="checkbox" x-model="field.searchable" class="rounded border-border">
                            {{ __('baobab::admin.studio.entities.field_searchable') }}
                        </label>
                        <label class="flex items-center gap-1 text-xs text-muted" x-show="field.searchable" x-cloak>
                            {{ __('baobab::admin.studio.entities.field_weight') }}
                            <input
                                type="number"
                                min="1"
                                x-model.number="field.weight"
                                aria-label="{{ __('baobab::admin.studio.entities.field_weight') }}"
                                class="w-14 rounded-md border border-border px-1 py-0.5 text-sm text-foreground"
                            >
                        </label>
                    @endif

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

                @if ($extraFields)
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <input
                            type="text"
                            x-model="field.label"
                            placeholder="{{ __('baobab::admin.forms.field_label_placeholder') }}"
                            aria-label="{{ __('baobab::admin.forms.field_label') }}"
                            class="rounded-md border border-border px-2 py-1 text-sm text-foreground"
                        >
                        <input
                            type="text"
                            x-model="field.placeholder"
                            placeholder="{{ __('baobab::admin.forms.field_placeholder_placeholder') }}"
                            aria-label="{{ __('baobab::admin.forms.field_placeholder') }}"
                            class="rounded-md border border-border px-2 py-1 text-sm text-foreground"
                        >
                        <input
                            type="text"
                            x-model="field.help_text"
                            placeholder="{{ __('baobab::admin.forms.field_help_text_placeholder') }}"
                            aria-label="{{ __('baobab::admin.forms.field_help_text') }}"
                            class="rounded-md border border-border px-2 py-1 text-sm text-foreground"
                        >
                    </div>
                @endif

                {{-- `choices` est obligatoire pour select/multiselect/radio (et leurs
                     alias formulaires select/checkboxes/radio) --}}
                <div class="mt-2" x-show="needsChoices(field.type)" x-cloak>
                    <label class="mb-1 block text-xs text-muted">{{ __('baobab::admin.studio.entities.field_choices') }}</label>
                    <textarea
                        x-model="field._choicesText"
                        rows="3"
                        class="w-full rounded-md border border-border px-2 py-1 font-mono text-sm text-foreground"
                    ></textarea>
                </div>

                @if ($extraFields)
                    {{-- Options propres au champ `consent` (spec 14 §2.2) : texte
                         légal et lien vers la politique de confidentialité. Type
                         sans objet ailleurs que dans un formulaire, donc jamais
                         affiché quand `extraFields` est faux. --}}
                    <div class="mt-2 space-y-2" x-show="field.type === 'consent'" x-cloak>
                        <div>
                            <label class="mb-1 block text-xs text-muted">{{ __('baobab::admin.forms.consent_text') }}</label>
                            <textarea
                                x-model="field._consentText"
                                rows="2"
                                class="w-full rounded-md border border-border px-2 py-1 text-sm text-foreground"
                            ></textarea>
                        </div>
                        <input
                            type="url"
                            x-model="field._consentPrivacyUrl"
                            placeholder="{{ __('baobab::admin.forms.consent_privacy_url_placeholder') }}"
                            aria-label="{{ __('baobab::admin.forms.consent_privacy_url') }}"
                            class="w-full rounded-md border border-border px-2 py-1 text-sm text-foreground"
                        >
                    </div>
                @endif
            </div>
        </template>
    </div>

    <x-baobab::button type="button" variant="secondary" class="mt-2" x-on:click="{{ $addExpression }}">
        {{ __('baobab::admin.studio.entities.add_field') }}
    </x-baobab::button>
</div>
