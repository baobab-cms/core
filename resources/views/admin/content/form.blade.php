@extends('baobab::layouts.admin')

@php
    $label = $contentType->blueprint['label']['singular'] ?? $contentType->key;
    $isEdit = $entry !== null;
    $titleField = $contentType->blueprint['title_field'] ?? null;
@endphp

@section('title', $label)

@section('content')
    <x-baobab::page :title="$label">
        <x-baobab::card>
            <x-baobab::form
                method="{{ $isEdit ? 'PUT' : 'POST' }}"
                action="{{ $isEdit
                    ? route('admin.content.update', ['contentType' => $slug, 'entry' => $entry->id])
                    : route('admin.content.store', ['contentType' => $slug]) }}"
            >
                <div x-data="{ slugManuallyEdited: {{ $isEdit ? 'true' : 'false' }} }">
                    @foreach ($fields as $field)
                        @php
                            $name = $field['key'];
                            $value = $entry?->getAttribute($name);
                            $fieldLabel = \Illuminate\Support\Str::headline($name);
                            $choices = $field['options']['choices'] ?? [];
                            $isTitleSource = $titleField !== null && $name === $titleField;
                            $autoSlugHandler = $isTitleSource
                                ? 'if (!slugManuallyEdited) { const el = document.getElementById(\'slug\'); if (el) { el.value = baobabSlugify($event.target.value); } }'
                                : '';
                        @endphp

                        @switch($field['type'])
                            @case('slug')
                                <x-baobab::field.text
                                    :name="$name"
                                    :label="$fieldLabel"
                                    :value="$value"
                                    placeholder="mon-titre-de-page"
                                    x-on:input="slugManuallyEdited = true"
                                />
                                @break

                            @case('boolean')
                                <x-baobab::field.checkbox :name="$name" :label="$fieldLabel" :checked="(bool) $value" />
                                @break

                            @case('select')
                            @case('radio')
                                <x-baobab::field.select
                                    :name="$name"
                                    :label="$fieldLabel"
                                    :options="array_combine($choices, $choices)"
                                    :value="$value"
                                />
                                @break

                            @case('multiselect')
                                <div class="mb-4">
                                    <label class="mb-1 block text-sm font-medium text-foreground">{{ $fieldLabel }}</label>
                                    <select
                                        name="{{ $name }}[]"
                                        multiple
                                        class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground"
                                    >
                                        @foreach ($choices as $choice)
                                            <option value="{{ $choice }}" @selected(in_array($choice, (array) old($name, $value ?? []), true))>
                                                {{ $choice }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error($name)
                                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                                @break

                            @case('textarea')
                            @case('richtext')
                                <x-baobab::field.textarea
                                    :name="$name"
                                    :label="$fieldLabel"
                                    :value="$value"
                                    x-on:input="{{ $autoSlugHandler }}"
                                />
                                @break

                            @case('json')
                                <x-baobab::field.textarea
                                    :name="$name"
                                    :label="$fieldLabel"
                                    :value="$value === null ? null : json_encode($value, JSON_PRETTY_PRINT)"
                                />
                                @break

                            @default
                                <x-baobab::field.text
                                    :name="$name"
                                    :label="$fieldLabel"
                                    :value="$value"
                                    :type="match ($field['type']) {
                                        'integer', 'decimal' => 'number',
                                        'date' => 'date',
                                        'datetime' => 'datetime-local',
                                        'time' => 'time',
                                        default => 'text',
                                    }"
                                    x-on:input="{{ $autoSlugHandler }}"
                                />
                        @endswitch
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.content.save_action') }}
                    </x-baobab::button>

                    <x-baobab::button :href="route('admin.content.index', ['contentType' => $slug])" variant="secondary">
                        {{ __('baobab::admin.content.cancel_action') }}
                    </x-baobab::button>
                </div>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>

    @once
        <script>
            function baobabSlugify(value) {
                const accents = { à:'a', â:'a', ä:'a', á:'a', ã:'a', å:'a', ç:'c', é:'e', è:'e', ê:'e', ë:'e', î:'i', ï:'i', ì:'i', í:'i', ô:'o', ö:'o', ò:'o', ó:'o', õ:'o', ù:'u', û:'u', ü:'u', ú:'u', ñ:'n', ý:'y', ÿ:'y' };

                return value
                    .toLowerCase()
                    .split('')
                    .map((char) => accents[char] ?? char)
                    .join('')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        </script>
    @endonce
@endsection
