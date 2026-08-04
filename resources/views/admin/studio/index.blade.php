@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.studio.title')">
        <x-slot:actions>
            <x-baobab::button href="{{ route('admin.studio.create') }}" variant="primary">
                {{ __('baobab::admin.studio.create_action') }}
            </x-baobab::button>
        </x-slot:actions>

        @if ($drafts->isEmpty())
            <x-baobab::empty-state :message="__('baobab::admin.studio.empty')" />
        @else
            <div class="space-y-4">
                @foreach ($drafts as $draft)
                    <x-baobab::card :header="$draft->title">
                        <div class="flex items-center justify-between gap-4">
                            <div class="space-y-1 text-sm text-muted">
                                <p>{{ $draft->vendor_slug }}</p>
                                @if ($draft->isGenerated())
                                    <x-baobab::badge variant="success">{{ __('baobab::admin.studio.generated') }}</x-baobab::badge>
                                @else
                                    <x-baobab::badge variant="neutral">
                                        {{ __('baobab::admin.studio.step_progress', ['current' => $draft->current_step]) }}
                                    </x-baobab::badge>
                                @endif
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.studio.show', $draft) }}" class="text-primary hover:underline">
                                    {{ __('baobab::admin.studio.resume_action') }}
                                </a>

                                @unless ($draft->isGenerated())
                                    <form method="POST" action="{{ route('admin.studio.destroy', $draft) }}" onsubmit="return confirm('{{ __('baobab::admin.studio.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-danger hover:underline">{{ __('baobab::admin.studio.delete_draft') }}</button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    </x-baobab::card>
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
