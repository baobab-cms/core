@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.users.invite.title'))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.users.invite.title')"
        :breadcrumbs="[[__('baobab::admin.users.title'), route('admin.users.index')], [__('baobab::admin.users.invite.title')]]"
    >
        <x-baobab::card>
            <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.users.invite.intro') }}</p>

            <x-baobab::form method="POST" action="{{ route('admin.users.store') }}" class="max-w-md">
                <x-baobab::field.text name="name" label="{{ __('baobab::admin.users.invite.name_label') }}" autocomplete="off" />
                <x-baobab::field.text type="email" name="email" label="{{ __('baobab::admin.users.invite.email_label') }}" autocomplete="off" />

                <div class="mb-4">
                    <label for="role" class="mb-1 block text-sm font-medium text-foreground">
                        {{ __('baobab::admin.users.invite.role_label') }}
                    </label>
                    <select
                        id="role" name="role"
                        aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}"
                        @if ($errors->has('role')) aria-describedby="role-error" @endif
                        class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
                    >
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('role') === $role->name)>
                                {{ $role->name }} ({{ $role->level }})
                            </option>
                        @endforeach
                    </select>
                    <x-baobab::field.error name="role" />
                </div>

                <x-baobab::button type="submit" variant="primary">
                    {{ __('baobab::admin.users.invite.submit') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>
    </x-baobab::page>
@endsection
