@props(['fields', 'required' => false])

{{--
    Dispatch d'un champ de formulaire (spec 14 §2.2) vers son composant
    `field.*` — patron `AdminCrudGenerator`/`FieldType::formComponent()`, mais
    en dispatch d'exécution plutôt qu'à la génération (aucun autre écran du
    Core ne le fait, cf. suivi n° 260).

    Partagé par l'aperçu du builder (`admin/forms/edit.blade.php`, Pass B2) et
    le rendu public (`<x-baobab::form-embed>`, Pass C1/C2/C3) — spec 14 §3 : « aperçu
    rendu avec le vrai composant front ». `$required` distingue les deux : le
    builder envoie un aperçu statique ($required=false, aucune contrainte
    HTML5 utile sur des noms de champs fictifs) ; le rendu public la passe à
    `true` pour que le navigateur bloque avant même la requête serveur.
--}}
@foreach ($fields as $field)
    @switch($field['type'])
        @case('email')
            <x-baobab::field.email :name="$field['key']" :label="$field['display_label']" :placeholder="$field['placeholder']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('tel')
            <x-baobab::field.tel :name="$field['key']" :label="$field['display_label']" :placeholder="$field['placeholder']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('url')
            <x-baobab::field.url :name="$field['key']" :label="$field['display_label']" :placeholder="$field['placeholder']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('number')
            <x-baobab::field.integer :name="$field['key']" :label="$field['display_label']" :placeholder="$field['placeholder']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('date')
            <x-baobab::field.date :name="$field['key']" :label="$field['display_label']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('textarea')
            <x-baobab::field.textarea :name="$field['key']" :label="$field['display_label']" :placeholder="$field['placeholder']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('select')
            <x-baobab::field.select :name="$field['key']" :label="$field['display_label']" :options="$field['choice_options']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('radio')
            <x-baobab::field.radio :name="$field['key']" :label="$field['display_label']" :options="$field['choice_options']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('checkbox')
            <x-baobab::field.checkbox :name="$field['key']" :label="$field['display_label']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('checkboxes')
            <x-baobab::field.multiselect :name="$field['key']" :label="$field['display_label']" :options="$field['choice_options']" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('consent')
            <x-baobab::field.consent :name="$field['key']" :text="(string) ($field['options']['text'] ?? '')" :privacy-url="$field['options']['privacy_url'] ?? null" :required="$required && ($field['required'] ?? false)" />
            @break

        @case('file')
            <x-baobab::field.file :name="$field['key']" :label="$field['display_label']" :required="$required && ($field['required'] ?? false)" />
            @break

        @default
            <x-baobab::field.text :name="$field['key']" :label="$field['display_label']" :placeholder="$field['placeholder']" :required="$required && ($field['required'] ?? false)" />
    @endswitch

    {{-- Aucun composant field.* ne porte de zone d'aide : construits pour les
         fiches Content Type, qui n'en ont pas (spec 02). Rendue ici, hors du
         composant, plutôt que d'en modifier quatorze pour ce seul appelant.
         Le champ `consent` porte déjà son texte légal dans son propre
         composant : une aide y ferait doublon. --}}
    @if (($field['help_text'] ?? null) && $field['type'] !== 'consent')
        <p class="-mt-3 mb-4 text-xs text-muted">{{ $field['help_text'] }}</p>
    @endif
@endforeach
