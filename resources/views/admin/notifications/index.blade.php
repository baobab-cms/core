@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.notifications.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.notifications.title')">
        <x-baobab::card>
            @if ($notifications->isEmpty())
                <x-baobab::empty-state :message="__('baobab::admin.notifications.empty')" />
            @else
                <ul class="divide-y divide-border">
                    @foreach ($notifications as $notification)
                        <li class="flex items-center justify-between gap-4 py-3 text-sm {{ $notification['read'] ? 'text-muted' : 'text-foreground' }}">
                            <div>
                                @if ($notification['url'])
                                    <a href="{{ $notification['url'] }}" class="hover:underline">{{ $notification['description'] }}</a>
                                @else
                                    {{ $notification['description'] }}
                                @endif
                                <p class="text-xs text-muted">{{ $notification['created_at'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{ $notifications->links() }}
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection
