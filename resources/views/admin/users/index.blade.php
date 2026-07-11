@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.users.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.users.title')">
        @php
            $columns = [
                ['key' => 'name', 'label' => __('baobab::admin.users.column_name')],
                ['key' => 'email', 'label' => __('baobab::admin.users.column_email')],
                [
                    'key' => 'roles',
                    'label' => __('baobab::admin.users.column_roles'),
                    'render' => fn ($user) => $user->roles->pluck('name')->join(', ') ?: '—',
                ],
                [
                    'key' => 'level',
                    'label' => __('baobab::admin.users.column_level'),
                    'render' => fn ($user) => (string) $user->level(),
                ],
                [
                    'key' => 'actions',
                    'label' => '',
                    'raw' => true,
                    'render' => function ($user) use ($actor) {
                        if ($user->is($actor) || $user->level() >= $actor->level()) {
                            return '';
                        }

                        return view('baobab::admin.users.partials.impersonate-button', ['user' => $user])->render();
                    },
                ],
            ];
        @endphp

        <x-baobab::table :columns="$columns" :rows="$users" />
    </x-baobab::page>
@endsection
