@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.trash.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.trash.title')">
        @if (empty($groups))
            <x-baobab::empty-state :message="__('baobab::admin.trash.empty')" />
        @else
            <div class="space-y-6">
                @foreach ($groups as $group)
                    <x-baobab::card :header="$group['label']">
                        <ul class="divide-y divide-border">
                            @foreach ($group['rows'] as $row)
                                <li class="flex items-center justify-between gap-4 py-3 text-sm">
                                    <div>
                                        <p class="font-medium text-foreground">
                                            @if ($group['displayField'] && $row->{$group['displayField']})
                                                {{ $row->{$group['displayField']} }}
                                            @else
                                                #{{ $row->id }}
                                            @endif
                                        </p>
                                        <p class="text-xs text-muted">{{ __('baobab::admin.trash.deleted_at_label') }} {{ $row->deleted_at->format('d/m/Y H:i') }}</p>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <form method="POST" action="{{ route('admin.content.restore', ['contentType' => $group['slug'], 'entry' => $row->id]) }}">
                                            @csrf
                                            <button type="submit" class="text-primary hover:underline">{{ __('baobab::admin.content.restore_action') }}</button>
                                        </form>

                                        @if ($canPurge)
                                            <x-baobab::delete-action
                                                name="purge-trash-{{ $group['slug'] }}-{{ $row->id }}"
                                                :action="route('admin.content.force-destroy', ['contentType' => $group['slug'], 'entry' => $row->id])"
                                                :title="__('baobab::admin.components.delete.purge_generic')"
                                                :description="__('baobab::admin.components.delete.irreversible')"
                                                :label="__('baobab::admin.content.purge_action')"
                                            />
                                        @endif

                                        <a href="{{ route('admin.content.index', ['contentType' => $group['slug'], 'trashed' => 1]) }}" class="text-muted hover:underline">
                                            {{ __('baobab::admin.trash.view_type_action') }}
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-baobab::card>
                @endforeach
            </div>
        @endif
    </x-baobab::page>
@endsection
