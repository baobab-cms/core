@extends('baobab::layouts.admin')

@section('title', $label)

@section('content')
    <x-baobab::page :title="$label">
        <x-baobab::card>
            <x-baobab::form method="{{ $formMethod }}" action="{{ $formAction }}">
                <div x-data="{ slugManuallyEdited: {{ $isEdit ? 'true' : 'false' }} }">
                    @foreach ($fields as $field)
                        @switch($field['type'])
                            @case('slug')
                                <x-baobab::field.text
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :value="$field['value']"
                                    placeholder="mon-titre-de-page"
                                    x-on:input="slugManuallyEdited = true"
                                />
                                @break

                            @case('boolean')
                                <x-baobab::field.checkbox :name="$field['key']" :label="$field['label']" :checked="(bool) $field['value']" />
                                @break

                            @case('select')
                            @case('radio')
                                <x-baobab::field.select
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :options="$field['choice_options']"
                                    :value="$field['value']"
                                />
                                @break

                            @case('multiselect')
                                <div class="mb-4">
                                    <label class="mb-1 block text-sm font-medium text-foreground">{{ $field['label'] }}</label>
                                    <select
                                        name="{{ $field['key'] }}[]"
                                        multiple
                                        class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground"
                                    >
                                        @foreach ($field['choices'] as $choice)
                                            <option value="{{ $choice }}" @selected(in_array($choice, (array) old($field['key'], $field['value'] ?? []), true))>
                                                {{ $choice }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error($field['key'])
                                        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                    @enderror
                                </div>
                                @break

                            @case('textarea')
                                <x-baobab::field.textarea
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :value="$field['value']"
                                    x-on:input="{{ $field['auto_slug_handler'] }}"
                                />
                                @break

                            @case('richtext')
                                <x-baobab::field.richtext
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :value="$field['value']"
                                    x-on:input="{{ $field['auto_slug_handler'] }}"
                                />
                                @break

                            @case('json')
                                <x-baobab::field.textarea
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :value="$field['json_value']"
                                />
                                @break

                            @case('image')
                                <x-baobab::field.media
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    type="image"
                                    :media="$field['media']"
                                />
                                @break

                            @case('file')
                                <x-baobab::field.media
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    type="file"
                                    :media="$field['media']"
                                />
                                @break

                            @case('gallery')
                                <x-baobab::field.gallery
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :items="$field['gallery_items']"
                                />
                                @break

                            @default
                                <x-baobab::field.text
                                    :name="$field['key']"
                                    :label="$field['label']"
                                    :value="$field['value']"
                                    :type="$field['html_type']"
                                    x-on:input="{{ $field['auto_slug_handler'] }}"
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
