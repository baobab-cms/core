@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.notifications.preferences_title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.notifications.preferences_title')">
        <x-baobab::card>
            @if (empty($declarations))
                <x-baobab::empty-state :message="__('baobab::admin.notifications.preferences_empty')" />
            @else
                <x-baobab::form method="POST" action="{{ route('admin.account.notifications.update') }}">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-left text-xs uppercase text-muted">
                                    <th class="py-2">{{ __('baobab::admin.notifications.column_notification') }}</th>
                                    <th class="py-2 text-center">{{ __('baobab::admin.notifications.channel_database') }}</th>
                                    <th class="py-2 text-center">{{ __('baobab::admin.notifications.channel_mail') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($declarations as $declaration)
                                    <tr>
                                        <td class="py-3 text-foreground">{{ $declaration->description ?? $declaration->key }}</td>
                                        @foreach (['database', 'mail'] as $channel)
                                            <td class="py-3 text-center">
                                                @if (in_array($channel, $declaration->channels, true))
                                                    <input
                                                        type="checkbox"
                                                        name="prefs[{{ $declaration->key }}][{{ $channel }}]"
                                                        value="1"
                                                        class="rounded border-border"
                                                        @checked(! in_array("{$declaration->key}:{$channel}", $disabled, true))
                                                    >
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-baobab::button type="submit" variant="primary" class="mt-4">
                        {{ __('baobab::admin.notifications.save_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            @endif
        </x-baobab::card>
    </x-baobab::page>
@endsection
