@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.content.revisions_title', ['label' => $label]))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.content.revisions_title', ['label' => $label])"
        :breadcrumbs="[[__('baobab::admin.content.back_to_edit_action'), route('admin.content.edit', ['contentType' => $slug, 'entry' => $entryId])]]"
    >
        @if ($workingDraft)
            <x-baobab::card class="mb-6 border-primary/30 bg-primary/5">
                <p class="text-sm text-foreground">{{ __('baobab::admin.content.working_draft_banner') }}</p>
            </x-baobab::card>
        @endif

        <x-baobab::card class="mb-6" :header="__('baobab::admin.content.compare_revisions_title')">
            <form method="GET" action="{{ route('admin.content.revisions', ['contentType' => $slug, 'entry' => $entryId]) }}" class="flex flex-wrap items-end gap-2">
                <x-baobab::field.select
                    name="from"
                    label="{{ __('baobab::admin.content.compare_from_label') }}"
                    :options="['' => '—'] + $history->pluck('created_at', 'id')->map(fn ($date) => $date->format('d/m/Y H:i'))->all()"
                    :value="$fromId"
                />
                <x-baobab::field.select
                    name="to"
                    label="{{ __('baobab::admin.content.compare_to_label') }}"
                    :options="['' => '—'] + $history->pluck('created_at', 'id')->map(fn ($date) => $date->format('d/m/Y H:i'))->all()"
                    :value="$toId"
                />
                {{-- `mb-4` (suivi n° 381) --}}
                <x-baobab::button type="submit" variant="secondary" class="mb-4">{{ __('baobab::admin.content.compare_action') }}</x-baobab::button>
            </form>

            @if ($diff)
                <div class="mt-4 border-t border-border pt-4">
                    @include('baobab::admin.content.partials.diff', ['diff' => $diff])
                </div>
            @endif
        </x-baobab::card>

        <x-baobab::card :header="__('baobab::admin.content.revisions_history_title')">
            @if ($history->isEmpty())
                <x-baobab::empty-state :message="__('baobab::admin.content.revisions_empty')" />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($history as $revision)
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div>
                                <p class="font-medium text-foreground">
                                    {{ $revision->created_at->format('d/m/Y H:i') }}
                                    <span class="ml-2 text-xs text-muted">{{ $revision->author->name ?? __('baobab::admin.content.revision_system_author') }}</span>
                                    @if ($revision->type === 'pre_restore')
                                        <span class="ml-2 rounded bg-surface-subtle px-1.5 py-0.5 text-xs text-muted">{{ __('baobab::admin.content.revision_pre_restore_badge') }}</span>
                                    @endif
                                </p>
                                @if ($revision->summary)
                                    <p class="text-muted">{{ $revision->summary }}</p>
                                @endif
                            </div>

                            @if ($canUpdate)
                                <form
                                    method="POST"
                                    action="{{ route('admin.content.revisions.restore', ['contentType' => $slug, 'entry' => $entryId, 'revision' => $revision->id]) }}"
                                    onsubmit="return confirm('{{ __('baobab::admin.content.revision_restore_confirm_title') }}')"
                                >
                                    @csrf
                                    <button type="submit" class="text-primary hover:underline">{{ __('baobab::admin.content.restore_action') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection
