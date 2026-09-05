@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.form_submissions.title', ['form' => $form->title]))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.form_submissions.title', ['form' => $form->title])"
        :breadcrumbs="[[__('baobab::admin.forms.title'), route('admin.forms.index')]]"
    >
        <x-baobab::card class="mb-6">
            <x-baobab::form method="GET" action="{{ route('admin.forms.submissions.index', ['form' => $form->id]) }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="status" class="mb-1 block text-xs text-muted">{{ __('baobab::admin.form_submissions.filter_status') }}</label>
                    <select id="status" name="status" class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="from" class="mb-1 block text-xs text-muted">{{ __('baobab::admin.form_submissions.filter_from') }}</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground">
                </div>

                <div>
                    <label for="to" class="mb-1 block text-xs text-muted">{{ __('baobab::admin.form_submissions.filter_to') }}</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" class="rounded-md border border-border bg-surface px-2 py-1 text-sm text-foreground">
                </div>

                <x-baobab::button type="submit" variant="secondary">{{ __('baobab::admin.form_submissions.filter_submit') }}</x-baobab::button>

                {{--
                    Même filtre, même formulaire, une autre destination — le
                    patron `formaction` de l'aperçu des e-mails (M8 point 7,
                    Pass A2) : exporter le résultat filtré sans une ligne de
                    JavaScript, la requête GET porte déjà status/from/to.
                --}}
                <x-baobab::button
                    type="submit"
                    variant="secondary"
                    class="ml-auto"
                    formmethod="GET"
                    formaction="{{ route('admin.forms.submissions.export', ['form' => $form->id]) }}"
                >
                    {{ __('baobab::admin.form_submissions.export_action') }}
                </x-baobab::button>
            </x-baobab::form>
        </x-baobab::card>

        <x-baobab::table :columns="$columns" :rows="$submissions" />
    </x-baobab::page>
@endsection
