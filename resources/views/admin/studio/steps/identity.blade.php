@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <x-baobab::card :header="$handler->label()">
            <x-baobab::form method="POST" action="{{ $formAction }}">
                <x-baobab::field.text
                    name="name"
                    :label="__('baobab::admin.studio.identity.name')"
                    :value="$values['name']"
                    placeholder="acme/blog"
                />

                <x-baobab::field.text
                    name="title"
                    :label="__('baobab::admin.studio.identity.title')"
                    :value="$values['title']"
                />

                <x-baobab::field.textarea
                    name="description"
                    :label="__('baobab::admin.studio.identity.description')"
                    :value="$values['description']"
                    rows="2"
                />

                <x-baobab::field.text
                    name="icon"
                    :label="__('baobab::admin.studio.identity.icon')"
                    :value="$values['icon']"
                    placeholder="bi-box-seam"
                    list="baobab-icon-catalogue"
                />

                <p class="mb-4 -mt-2 text-xs text-muted">{{ __('baobab::admin.studio.identity.icon_hint') }}</p>

                @include('baobab::admin.studio.steps.partials.icon-catalogue')

                <x-baobab::field.text
                    name="version"
                    :label="__('baobab::admin.studio.identity.version')"
                    :value="$values['version']"
                    placeholder="1.0.0"
                />

                <x-baobab::field.textarea
                    name="authors"
                    :label="__('baobab::admin.studio.identity.authors')"
                    :value="$values['authors']"
                    rows="3"
                    placeholder="Ada Lovelace <ada@example.com> (https://example.com)"
                />

                <p class="mb-4 -mt-2 text-xs text-muted">{{ __('baobab::admin.studio.identity.authors_hint') }}</p>

                <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-2 text-xs text-muted">
                    {{ __('baobab::admin.studio.identity.type_locked') }}
                </p>

                <div class="flex items-center justify-between">
                    <x-baobab::button :href="route('admin.studio.index')" variant="ghost">
                        {{ __('baobab::admin.studio.back_to_list') }}
                    </x-baobab::button>

                    <x-baobab::button type="submit" variant="primary">
                        {{ $isLastImplementedStep ? __('baobab::admin.studio.save') : __('baobab::admin.studio.save_and_continue') }}
                    </x-baobab::button>
                </div>
            </x-baobab::form>
        </x-baobab::card>

        @if ($draft && ! $draft->isGenerated())
            <form method="POST" action="{{ route('admin.studio.destroy', $draft) }}" class="mt-4" onsubmit="return confirm('{{ __('baobab::admin.studio.confirm_delete') }}')">
                @csrf
                @method('DELETE')
                <x-baobab::button type="submit" variant="danger">{{ __('baobab::admin.studio.delete_draft') }}</x-baobab::button>
            </form>
        @endif

        @if (! $draft)
            <p class="mt-6 text-xs text-muted">{{ __('baobab::admin.studio.more_steps_soon') }}</p>
        @endif
    </x-baobab::page>
@endsection
