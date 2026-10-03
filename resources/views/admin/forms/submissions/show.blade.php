@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.form_submissions.detail_title'))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.form_submissions.detail_title')"
        :breadcrumbs="[
            [__('baobab::admin.forms.title'), route('admin.forms.index')],
            [$form->title, route('admin.forms.submissions.index', ['form' => $form->id])],
        ]"
    >
        <x-baobab::card>
            <dl class="divide-y divide-border">
                <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-3">
                    <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.column_date') }}</dt>
                    <dd class="text-sm text-foreground sm:col-span-2">{{ $submission->created_at?->format('Y-m-d H:i') }}</dd>
                </div>

                <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-3">
                    <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.column_status') }}</dt>
                    <dd class="text-sm text-foreground sm:col-span-2">
                        <x-baobab::badge :variant="$submission->status->badgeVariant()">{{ $submission->status->label() }}</x-baobab::badge>
                    </dd>
                </div>

                @if ($submission->consent_at !== null)
                    <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-3">
                        <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.consent_at') }}</dt>
                        <dd class="text-sm text-foreground sm:col-span-2">{{ $submission->consent_at->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif

                @if ($submission->ip !== null)
                    <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-3">
                        <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.ip') }}</dt>
                        <dd class="text-sm text-foreground sm:col-span-2">{{ $submission->ip }}</dd>
                    </div>
                @endif

                @foreach ($fields as $field)
                    <div class="grid grid-cols-1 gap-2 py-2 sm:grid-cols-3">
                        <dt class="text-sm font-medium text-foreground">{{ $field['label'] }}</dt>
                        <dd class="whitespace-pre-wrap text-sm text-foreground sm:col-span-2">
                            @if ($field['raw'] ?? false)
                                {!! $field['value'] !!}
                            @else
                                {{ $field['value'] }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-baobab::card>
    </x-baobab::page>
@endsection
