@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.privacy_register.title'))

@section('content')
    <x-baobab::page :title="__('baobab::admin.privacy_register.title')">
        <x-slot:actions>
            <x-baobab::button :href="route('admin.privacy.register.export')" variant="secondary">
                {{ __('baobab::admin.privacy_register.export') }}
            </x-baobab::button>
        </x-slot:actions>

        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.privacy_register.intro') }}</p>

        @if ($register->undeclaredModules !== [])
            <div role="alert" class="mb-4 rounded-lg border border-warning bg-surface p-4 text-sm text-foreground">
                <p class="font-medium">{{ __('baobab::admin.privacy_register.undeclared_title') }}</p>
                <p class="mt-1 text-muted">{{ __('baobab::admin.privacy_register.undeclared_help') }}</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($register->undeclaredModules as $moduleName)
                        <li>{{ $moduleName }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-4">
            @foreach ($register->declarations as $key => $declaration)
                <x-baobab::card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-medium text-foreground">{{ $declaration->title }}</h2>
                            <span class="text-xs text-muted">{{ $key }}</span>
                        </div>
                    </x-slot:header>

                    <dl class="grid gap-3 text-sm sm:grid-cols-[12rem_1fr]">
                        <dt class="text-muted">{{ __('baobab::admin.privacy_register.nature') }}</dt>
                        <dd class="text-foreground">{{ $declaration->nature }}</dd>

                        <dt class="text-muted">{{ __('baobab::admin.privacy_register.purpose') }}</dt>
                        <dd class="text-foreground">{{ $declaration->purpose }}</dd>

                        <dt class="text-muted">{{ __('baobab::admin.privacy_register.legal_basis') }}</dt>
                        <dd class="text-foreground">{{ $declaration->legalBasis }}</dd>

                        <dt class="text-muted">{{ __('baobab::admin.privacy_register.retention') }}</dt>
                        <dd class="text-foreground">{{ $declaration->retention }}</dd>

                        <dt class="text-muted">{{ __('baobab::admin.privacy_register.external_services') }}</dt>
                        <dd class="text-foreground">
                            {{ $declaration->externalServices === [] ? __('baobab::admin.privacy_register.none') : implode(', ', $declaration->externalServices) }}
                        </dd>
                    </dl>
                </x-baobab::card>
            @endforeach
        </div>

        <h2 class="mb-3 mt-8 text-sm font-medium text-foreground">{{ __('baobab::admin.privacy_register.recipients_title') }}</h2>

        @if ($register->recipients === [])
            <div class="rounded-lg border border-border bg-surface">
                <x-baobab::empty-state :message="__('baobab::admin.privacy_register.no_recipients')" />
            </div>
        @else
            <x-baobab::card>
                <ul class="list-inside list-disc text-sm text-foreground">
                    @foreach ($register->recipients as $recipient)
                        <li>{{ $recipient }}</li>
                    @endforeach
                </ul>
            </x-baobab::card>
        @endif
    </x-baobab::page>
@endsection
