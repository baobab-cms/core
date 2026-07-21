@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.branding.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.branding.title')">
        <x-baobab::card>
            <x-baobab::form method="POST" action="{{ route('admin.branding.update') }}">
                <h2 class="mb-4 text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_identity') }}</h2>

                <x-baobab::field.media
                    name="logo_media_id"
                    label="{{ __('baobab::admin.branding.logo_label') }}"
                    type="image"
                    :media="$setting->logo"
                />

                <x-baobab::field.media
                    name="favicon_media_id"
                    label="{{ __('baobab::admin.branding.favicon_label') }}"
                    type="image"
                    :media="$setting->favicon"
                />

                <x-baobab::field.text
                    type="color"
                    name="primary_color"
                    label="{{ __('baobab::admin.branding.primary_color_label') }}"
                    :value="$colors['primary']"
                />

                <h2 class="mb-4 mt-8 text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_colors') }}</h2>

                @foreach ($colors as $key => $value)
                    @if ($key !== 'primary')
                        <x-baobab::field.text
                            type="color"
                            name="tokens[colors][{{ $key }}]"
                            label="{{ __('baobab::admin.branding.color_'.$key.'_label') }}"
                            :value="$value"
                        />
                    @endif
                @endforeach

                <h2 class="mb-4 mt-8 text-lg font-semibold text-foreground">{{ __('baobab::admin.branding.section_fonts') }}</h2>

                @foreach ($fonts as $key => $value)
                    <x-baobab::field.text
                        name="tokens[fonts][{{ $key }}]"
                        label="{{ __('baobab::admin.branding.font_'.$key.'_label') }}"
                        :value="$value"
                    />
                @endforeach

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::admin.branding.save_action') }}</x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
