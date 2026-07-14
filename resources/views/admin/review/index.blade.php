@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.review.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.review.title')">
        <form method="GET" action="{{ route('admin.review.index') }}" class="mb-6 flex flex-wrap items-end gap-2">
            <x-baobab::field.select
                name="type"
                label="{{ __('baobab::admin.review.filter_type') }}"
                :options="['' => __('baobab::admin.review.filter_all_types')] + collect($groups)->pluck('label', 'slug')->all()"
                :value="$filters['type'] ?? null"
            />
            <x-baobab::field.select
                name="author_id"
                label="{{ __('baobab::admin.review.filter_author') }}"
                :options="['' => __('baobab::admin.review.filter_all_authors')] + $authors->pluck('name', 'id')->all()"
                :value="$filters['author_id'] ?? null"
            />
            <x-baobab::field.text type="date" name="from" label="{{ __('baobab::admin.review.filter_from') }}" :value="$filters['from'] ?? null" />
            <x-baobab::field.text type="date" name="to" label="{{ __('baobab::admin.review.filter_to') }}" :value="$filters['to'] ?? null" />
            <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.review.filter_submit') }}</x-baobab::button>
        </form>

        @if (empty($groups))
            <x-baobab::empty-state :message="__('baobab::admin.review.empty')" />
        @else
            <div class="space-y-6">
                @foreach ($groups as $group)
                    <x-baobab::card :header="$group['label']">
                        <ul class="divide-y divide-border">
                            @foreach ($group['submissions'] as $row)
                                <li class="flex items-center justify-between gap-4 py-3 text-sm">
                                    <div>
                                        <p class="font-medium text-foreground">
                                            @if ($group['displayField'] && $row->{$group['displayField']})
                                                {{ $row->{$group['displayField']} }}
                                            @else
                                                #{{ $row->id }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-muted">{{ __('baobab::admin.review.submission_badge') }} · {{ $row->updated_at->format('d/m/Y H:i') }}</p>
                                    </div>

                                    <a href="{{ route('admin.content.edit', ['contentType' => $group['slug'], 'entry' => $row->id]) }}" class="text-primary hover:underline">
                                        {{ __('baobab::admin.review.examine_action') }}
                                    </a>
                                </li>
                            @endforeach

                            @foreach ($group['pendingDrafts'] as $draft)
                                <li class="flex items-center justify-between gap-4 py-3 text-sm">
                                    <div>
                                        <p class="font-medium text-foreground">
                                            @if ($group['displayField'] && ($draft->snapshot[$group['displayField']] ?? null))
                                                {{ $draft->snapshot[$group['displayField']] }}
                                            @else
                                                #{{ $draft->revisionable_id }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-muted">
                                            {{ __('baobab::admin.review.working_draft_badge') }}
                                            · {{ $draft->author->name ?? __('baobab::admin.content.revision_system_author') }}
                                            · {{ $draft->updated_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>

                                    <a href="{{ route('admin.content.edit', ['contentType' => $group['slug'], 'entry' => $draft->revisionable_id]) }}" class="text-primary hover:underline">
                                        {{ __('baobab::admin.review.examine_action') }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-baobab::card>
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
