@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.studio.title'))

@section('content')
    <x-baobab::page
        :title="$draft?->title ?? __('baobab::admin.studio.new_module')"
        :breadcrumbs="[[__('baobab::admin.sidebar.studio'), route('admin.studio.index')]]"
    >
        <x-baobab::wizard :steps="$steps" />

        <x-baobab::form method="POST" action="{{ $formAction }}">
            <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.studio.policies.intro') }}</p>

            @if (empty($policies))
                <p class="mb-4 rounded-md border border-border bg-surface-subtle px-3 py-6 text-center text-sm text-muted">
                    {{ __('baobab::admin.studio.policies.no_entities') }}
                </p>
            @endif

            @if ($autoCrudDisabled && ! empty($policies))
                <p class="mb-4 rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-sm text-foreground">
                    {{ __('baobab::admin.studio.policies.auto_crud_disabled_warning') }}
                </p>
            @endif

            <div class="space-y-3">
                @foreach ($policies as $policy)
                    <div class="rounded-lg border border-border bg-surface p-4">
                        <p class="font-mono text-sm font-medium text-foreground">{{ $policy['file'] }}</p>
                        <p class="mt-0.5 mb-3 text-xs text-muted">{{ __('baobab::admin.studio.policies.entity_caption', ['entity' => $policy['entity']]) }}</p>

                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="pb-1 text-xs font-medium text-muted">{{ __('baobab::admin.studio.policies.column_method') }}</th>
                                    <th class="pb-1 text-xs font-medium text-muted">{{ __('baobab::admin.studio.policies.column_permission') }}</th>
                                    <th class="pb-1 text-xs font-medium text-muted">{{ __('baobab::admin.studio.policies.column_origin') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($policy['methods'] as $method)
                                    <tr class="border-b border-border last:border-0">
                                        <td class="py-1.5 font-mono text-sm text-foreground">{{ $method['method'] }}()</td>
                                        <td class="py-1.5 font-mono text-sm text-muted">{{ $method['permission'] }}</td>
                                        <td class="py-1.5 text-xs text-muted">
                                            {{ $method['custom'] ? __('baobab::admin.studio.policies.origin_custom') : __('baobab::admin.studio.policies.origin_crud') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-muted">{{ __('baobab::admin.studio.policies.editable_hint') }}</p>

            <div class="mt-10 flex items-center justify-between">
                <x-baobab::button :href="route('admin.studio.step.show', [$draft, 4])" variant="ghost">
                    {{ __('baobab::admin.studio.previous') }}
                </x-baobab::button>

                <x-baobab::button type="submit" variant="primary">
                    {{ $isLastImplementedStep ? __('baobab::admin.studio.save') : __('baobab::admin.studio.save_and_continue') }}
                </x-baobab::button>
            </div>
        </x-baobab::form>
    </x-baobab::page>
@endsection
