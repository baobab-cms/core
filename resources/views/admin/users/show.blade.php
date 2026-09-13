@extends('baobab::layouts.admin')

@section('title', $user->name)

@section('content')
    <x-baobab::page
        :title="$user->name"
        :breadcrumbs="[[__('baobab::admin.users.title'), route('admin.users.index')], [$user->name]]"
    >
        @if ($canImpersonate)
            <x-slot:actions>
                @include('baobab::admin.users.partials.impersonate-button', ['user' => $user])
            </x-slot:actions>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-baobab::card :header="__('baobab::admin.users.show.identity_title')">
                <p class="text-sm text-muted">{{ $user->email }}</p>
                <p class="mt-1 text-sm text-muted">
                    {{ __('baobab::admin.users.column_level') }}: {{ $user->level() }}
                </p>
            </x-baobab::card>

            <x-baobab::card :header="__('baobab::admin.users.show.roles_title')">
                @forelse ($user->roles as $role)
                    <p class="border-b border-border py-2 text-sm text-foreground last:border-0">
                        <a href="{{ route('admin.access.roles.show', ['role' => $role]) }}" class="hover:underline">
                            {{ $role->name }}
                        </a>
                    </p>
                @empty
                    <p class="text-sm text-muted">{{ __('baobab::admin.users.show.roles_empty') }}</p>
                @endforelse
            </x-baobab::card>
        </div>

        <x-baobab::card class="mt-6" :header="__('baobab::admin.users.show.activity_title')">
            <x-baobab::table :columns="$activityColumns" :rows="$activity" />
        </x-baobab::card>
    </x-baobab::page>
@endsection
