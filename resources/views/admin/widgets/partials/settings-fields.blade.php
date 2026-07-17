{{--
    Formulaire de réglages généré depuis `Widget::settingsSchema()` (spec 10
    §3.3 : « mêmes composants de champs que les Content Types »). Patron
    exact du `@switch` de `admin/content/form.blade.php` — pas de dispatch
    dynamique via `FieldType::formComponent()`, qui n'est appelé nulle part
    même pour les Content Types. `label` vient du schéma (calculé par le
    widget, pas dérivé ici) — même discipline que ContentController, qui
    calcule les libellés en dehors de la vue.
--}}
@foreach ($fields as $field)
    @php $value = $values[$field['key']] ?? ($field['default'] ?? null); @endphp

    @switch($field['type'])
        @case('select')
            <x-baobab::field.select
                :name="$field['key']"
                :label="$field['label']"
                :options="$field['choice_options']"
                :value="$value"
            />
            @break

        @case('boolean')
            <x-baobab::field.checkbox
                :name="$field['key']"
                :label="$field['label']"
                :checked="(bool) $value"
            />
            @break

        @case('textarea')
            <x-baobab::field.textarea
                :name="$field['key']"
                :label="$field['label']"
                :value="$value"
            />
            @break

        @case('richtext')
            <x-baobab::field.richtext
                :name="$field['key']"
                :label="$field['label']"
                :value="$value"
            />
            @break

        @default
            <x-baobab::field.text
                :name="$field['key']"
                :label="$field['label']"
                :value="$value"
                :type="$field['type'] === 'integer' ? 'number' : 'text'"
            />
    @endswitch
@endforeach
