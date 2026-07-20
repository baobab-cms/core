@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.account.api_tokens.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.account.api_tokens.title')">
        <p class="mb-6 text-sm text-muted">{{ __('baobab::admin.account.api_tokens.intro') }}</p>

        @if ($plainTextToken)
            <x-baobab::card class="mb-6 border-warning/30">
                <x-slot:header>
                    <span class="font-medium text-foreground">{{ __('baobab::admin.account.api_tokens.plain_text_token_title') }}</span>
                </x-slot:header>

                <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.account.api_tokens.plain_text_token_warning') }}</p>

                <code class="block break-all rounded-md bg-surface-subtle px-3 py-2 font-mono text-sm text-foreground">{{ $plainTextToken }}</code>
            </x-baobab::card>
        @endif

        <x-baobab::card class="mb-6">
            <x-slot:header>
                <span class="font-medium text-foreground">{{ __('baobab::admin.account.api_tokens.new_token_title') }}</span>
            </x-slot:header>

            @if ($availablePermissions->isEmpty())
                <p class="text-sm text-muted">{{ __('baobab::admin.account.api_tokens.empty') }}</p>
            @else
                <x-baobab::form method="POST" action="{{ route('admin.account.api-tokens.store') }}">
                    <x-baobab::field.text
                        name="name"
                        label="{{ __('baobab::admin.account.api_tokens.name_label') }}"
                        placeholder="{{ __('baobab::admin.account.api_tokens.name_placeholder') }}"
                    />

                    <div class="mb-4" x-data="{ abilityQuery: '' }">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-medium text-foreground">
                                {{ __('baobab::admin.account.api_tokens.abilities_label') }}
                            </span>

                            <div class="flex items-center gap-2">
                                <input
                                    type="search"
                                    x-model="abilityQuery"
                                    placeholder="{{ __('baobab::admin.account.api_tokens.abilities_search_placeholder') }}"
                                    class="rounded-md border border-border bg-surface px-3 py-1.5 text-sm text-foreground"
                                >
                                <x-baobab::button
                                    type="button"
                                    variant="ghost"
                                    x-on:click="$el.closest('[x-data]').querySelectorAll('label[data-search]').forEach((label) => { if (abilityQuery === '' || label.dataset.search.includes(abilityQuery.toLowerCase())) label.querySelector('input').checked = true })"
                                >
                                    {{ __('baobab::admin.account.api_tokens.select_all_action') }}
                                </x-baobab::button>
                                <x-baobab::button
                                    type="button"
                                    variant="ghost"
                                    x-on:click="$el.closest('[x-data]').querySelectorAll('label[data-search]').forEach((label) => { if (abilityQuery === '' || label.dataset.search.includes(abilityQuery.toLowerCase())) label.querySelector('input').checked = false })"
                                >
                                    {{ __('baobab::admin.account.api_tokens.deselect_all_action') }}
                                </x-baobab::button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($availablePermissions as $permission)
                                <label
                                    class="flex items-center gap-2 text-sm text-foreground"
                                    data-search="{{ $permission['search'] }}"
                                    x-show="abilityQuery === '' || $el.dataset.search.includes(abilityQuery.toLowerCase())"
                                >
                                    <input
                                        type="checkbox"
                                        name="abilities[]"
                                        value="{{ $permission['name'] }}"
                                        class="rounded border-border"
                                    >
                                    {{ $permission['label'] }}
                                </label>
                            @endforeach
                        </div>

                        @error('abilities')
                            <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-baobab::field.text
                        type="datetime-local"
                        name="expires_at"
                        label="{{ __('baobab::admin.account.api_tokens.expires_at_label') }}"
                    />

                    <x-baobab::button type="submit" variant="primary">
                        {{ __('baobab::admin.account.api_tokens.create_action') }}
                    </x-baobab::button>
                </x-baobab::form>
            @endif
        </x-baobab::card>

        @if ($tokens->isNotEmpty())
            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-subtle text-xs uppercase text-muted">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ __('baobab::admin.account.api_tokens.column_name') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('baobab::admin.account.api_tokens.column_abilities') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('baobab::admin.account.api_tokens.column_last_used') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('baobab::admin.account.api_tokens.column_expires') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="px-3 py-2 text-foreground">{{ $token->name }}</td>
                                <td class="px-3 py-2">
                                    @foreach ((array) $token->abilities as $ability)
                                        <x-baobab::badge class="mb-1 mr-1">{{ $ability }}</x-baobab::badge>
                                    @endforeach
                                </td>
                                <td class="px-3 py-2 text-muted">
                                    {{ $token->last_used_at?->translatedFormat('d/m/Y H:i') ?? __('baobab::admin.account.api_tokens.never_used') }}
                                </td>
                                <td class="px-3 py-2 text-muted">
                                    {{ $token->expires_at?->translatedFormat('d/m/Y H:i') ?? __('baobab::admin.account.api_tokens.never_expires') }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <x-baobab::button
                                        type="button"
                                        variant="danger"
                                        x-on:click="$dispatch('open-modal', 'revoke-token-{{ $token->id }}')"
                                    >
                                        {{ __('baobab::admin.account.api_tokens.revoke_action') }}
                                    </x-baobab::button>

                                    <x-baobab::confirm
                                        name="revoke-token-{{ $token->id }}"
                                        :title="__('baobab::admin.account.api_tokens.revoke_confirm_title')"
                                    >
                                        <x-slot:description>
                                            {{ __('baobab::admin.account.api_tokens.revoke_confirm_description') }}
                                        </x-slot:description>

                                        <x-baobab::form method="DELETE" action="{{ route('admin.account.api-tokens.destroy', ['token' => $token->id]) }}">
                                            <x-baobab::button type="submit" variant="danger">
                                                {{ __('baobab::admin.account.api_tokens.revoke_action') }}
                                            </x-baobab::button>
                                        </x-baobab::form>
                                    </x-baobab::confirm>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-baobab::page>
@endsection
