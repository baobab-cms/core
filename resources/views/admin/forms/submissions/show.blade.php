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
                <div class="grid grid-cols-3 gap-2 py-2">
                    <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.column_date') }}</dt>
                    <dd class="col-span-2 text-sm text-foreground">{{ $submission->created_at?->format('Y-m-d H:i') }}</dd>
                </div>

                <div class="grid grid-cols-3 gap-2 py-2">
                    <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.column_status') }}</dt>
                    <dd class="col-span-2 text-sm text-foreground">
                        <x-baobab::badge :variant="$submission->status->badgeVariant()">{{ $submission->status->label() }}</x-baobab::badge>
                    </dd>
                </div>

                @if ($submission->consent_at !== null)
                    <div class="grid grid-cols-3 gap-2 py-2">
                        <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.consent_at') }}</dt>
                        <dd class="col-span-2 text-sm text-foreground">{{ $submission->consent_at->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif

                @if ($submission->ip !== null)
                    <div class="grid grid-cols-3 gap-2 py-2">
                        <dt class="text-sm font-medium text-foreground">{{ __('baobab::admin.form_submissions.ip') }}</dt>
                        <dd class="col-span-2 text-sm text-foreground">{{ $submission->ip }}</dd>
                    </div>
                @endif

                @foreach ($fields as $field)
                    <div class="grid grid-cols-3 gap-2 py-2">
                        <dt class="text-sm font-medium text-foreground">{{ $field['label'] }}</dt>
                        <dd class="col-span-2 whitespace-pre-wrap text-sm text-foreground">{{ $field['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-baobab::card>
    </x-baobab::page>
@endsection
