@extends('baobab::layouts.admin')

@section('title', $form->title)

@section('content')
    <x-baobab::page :title="$form->title" :breadcrumbs="[[__('baobab::admin.forms.title'), route('admin.forms.index')]]">
        <p class="mb-4 text-xs text-muted">
            {{ __('baobab::admin.forms.slug_display', ['slug' => $form->slug]) }}
            &middot;
            {{ __('baobab::admin.forms.version_display', ['version' => $form->version]) }}
        </p>

        <div
            x-data="formFieldsEditor({
                fields: @js($form->blueprint['fields'] ?? []),
                needChoices: @js($typesNeedingChoices),
            })"
        >
            <x-baobab::card>
                <x-baobab::form method="PUT" action="{{ route('admin.forms.update', ['form' => $form->id]) }}">
                    <input type="hidden" name="fields" x-bind:value="payload">

                    <x-baobab::field.text name="title" :label="__('baobab::admin.forms.title_label')" :value="$form->title" />

                    <x-baobab::blueprint.field-rows
                        collection="fields"
                        add-expression="addField()"
                        :field-types="$fieldTypes"
                        :extra-fields="true"
                        :show-unique-indexed="false"
                        :draggable="true"
                    />

                    <div class="mt-4">
                        <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.forms.save_action') }}</x-baobab::button>
                    </div>
                </x-baobab::form>
            </x-baobab::card>
        </div>

        <x-baobab::card class="mt-6">
            <h2 class="mb-3 font-display text-sm font-medium text-foreground">{{ __('baobab::admin.forms.preview_title') }}</h2>
            <p class="mb-4 text-xs text-muted">{{ __('baobab::admin.forms.preview_hint') }}</p>

            @if ($previewFields === [])
                <p class="text-sm text-muted">{{ __('baobab::admin.forms.preview_empty') }}</p>
            @else
                @foreach ($previewFields as $field)
                    @switch($field['type'])
                        @case('email')
                            <x-baobab::field.email :name="$field['key']" :label="$field['label'] ?? $field['key']" :placeholder="$field['placeholder']" />
                            @break

                        @case('tel')
                            <x-baobab::field.tel :name="$field['key']" :label="$field['label'] ?? $field['key']" :placeholder="$field['placeholder']" />
                            @break

                        @case('url')
                            <x-baobab::field.url :name="$field['key']" :label="$field['label'] ?? $field['key']" :placeholder="$field['placeholder']" />
                            @break

                        @case('number')
                            <x-baobab::field.integer :name="$field['key']" :label="$field['label'] ?? $field['key']" :placeholder="$field['placeholder']" />
                            @break

                        @case('date')
                            <x-baobab::field.date :name="$field['key']" :label="$field['label'] ?? $field['key']" />
                            @break

                        @case('textarea')
                            <x-baobab::field.textarea :name="$field['key']" :label="$field['label'] ?? $field['key']" :placeholder="$field['placeholder']" />
                            @break

                        @case('select')
                            <x-baobab::field.select :name="$field['key']" :label="$field['label'] ?? $field['key']" :options="$field['choice_options']" />
                            @break

                        @case('radio')
                            <x-baobab::field.radio :name="$field['key']" :label="$field['label'] ?? $field['key']" :options="$field['choice_options']" />
                            @break

                        @case('checkbox')
                            <x-baobab::field.checkbox :name="$field['key']" :label="$field['label'] ?? $field['key']" />
                            @break

                        @case('checkboxes')
                            <x-baobab::field.multiselect :name="$field['key']" :label="$field['label'] ?? $field['key']" :options="$field['choice_options']" />
                            @break

                        @case('consent')
                            <x-baobab::field.checkbox :name="$field['key']" :label="$field['options']['text'] ?? $field['label'] ?? $field['key']" />
                            @break

                        @default
                            <x-baobab::field.text :name="$field['key']" :label="$field['label'] ?? $field['key']" :placeholder="$field['placeholder']" />
                    @endswitch

                    {{-- Aucun composant field.* ne porte de zone d'aide : construits
                         pour les fiches Content Type, qui n'en ont pas (spec 02).
                         Rendue ici, hors du composant, plutôt que d'en modifier
                         quatorze pour un seul appelant. --}}
                    @if ($field['help_text'])
                        <p class="-mt-3 mb-4 text-xs text-muted">{{ $field['help_text'] }}</p>
                    @endif
                @endforeach
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection

@once
    <script>
        function formFieldsEditor(config) {
            return {
                needChoices: config.needChoices,

                fields: config.fields.map((field) => ({
                    key: field.key,
                    type: field.type,
                    label: field.label ?? '',
                    placeholder: field.placeholder ?? '',
                    help_text: field.help_text ?? '',
                    required: field.required ?? false,
                    _choicesText: (field.options?.choices ?? []).join('\n'),
                    _consentText: field.options?.text ?? '',
                    _consentPrivacyUrl: field.options?.privacy_url ?? '',
                })),

                /**
                 * Point de sérialisation unique : les clés d'interface
                 * (préfixées `_`) ne sortent jamais d'ici — patron
                 * `studioEntities().payload`.
                 */
                get payload() {
                    return JSON.stringify(this.fields.map((field) => {
                        let options = {};

                        if (field.type === 'consent') {
                            options = { text: field._consentText, privacy_url: field._consentPrivacyUrl };
                        } else if (this.needsChoices(field.type)) {
                            options = {
                                choices: (field._choicesText ?? '')
                                    .split('\n')
                                    .map((choice) => choice.trim())
                                    .filter(Boolean),
                            };
                        }

                        return {
                            key: field.key,
                            type: field.type,
                            label: field.label,
                            placeholder: field.placeholder,
                            help_text: field.help_text,
                            required: field.required,
                            options,
                        };
                    }));
                },

                needsChoices(type) {
                    return this.needChoices.includes(type);
                },

                addField() {
                    this.fields.push({
                        key: '',
                        type: 'text',
                        label: '',
                        placeholder: '',
                        help_text: '',
                        required: false,
                        _choicesText: '',
                        _consentText: '',
                        _consentPrivacyUrl: '',
                    });
                },
            };
        }
    </script>
@endonce
