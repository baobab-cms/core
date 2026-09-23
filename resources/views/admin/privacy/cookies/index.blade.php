@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.privacy_cookies.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.privacy_cookies.title')">
        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.privacy_cookies.intro') }}</p>

        <div role="status" class="mb-6 flex items-start gap-3 rounded-lg border border-border bg-surface p-4 text-sm">
            @if ($bannerShown)
                <x-baobab::badge variant="info">{{ __('baobab::admin.privacy_cookies.banner_shown') }}</x-baobab::badge>
                <p class="text-muted">{{ __('baobab::admin.privacy_cookies.banner_shown_help') }}</p>
            @else
                <x-baobab::badge>{{ __('baobab::admin.privacy_cookies.banner_hidden') }}</x-baobab::badge>
                <p class="text-muted">{{ __('baobab::admin.privacy_cookies.banner_hidden_help') }}</p>
            @endif
        </div>

        <div class="space-y-6">
            @foreach ($categories as $category)
                <section aria-labelledby="cookie-category-{{ $category['key'] }}">
                    <h2 id="cookie-category-{{ $category['key'] }}" class="text-sm font-medium text-foreground">{{ $category['label'] }}</h2>
                    <p class="mb-3 mt-1 text-sm text-muted">{{ $category['description'] }}</p>

                    @if ($category['rows'] === [])
                        <div class="rounded-lg border border-border bg-surface">
                            <x-baobab::empty-state :message="__('baobab::admin.privacy_cookies.empty')" />
                        </div>
                    @else
                        <x-baobab::table :columns="$columns" :rows="$category['rows']" />
                    @endif
                </section>
            @endforeach
        </div>
    </x-baobab::page>
@endsection
