@extends('baobab::layouts.admin')

@section('title', __('baobab::admin.mails.edit_title', ['key' => $declaration->key]))

@section('content')
    <x-baobab::page
        :title="__('baobab::admin.mails.edit_title', ['key' => $declaration->key])"
        :breadcrumbs="[[__('baobab::admin.mails.title'), route('admin.mails.index')]]"
    >
        <p class="mb-4 text-sm text-muted">{{ $declaration->description }}</p>

        @if ($drift !== null)
            {{--
                Le défaut du code a bougé depuis que l'admin a enregistré sa
                version. On montre ce qui a changé côté origine — jamais un
                choix à faire dans l'urgence : la version de l'admin reste en
                place tant qu'il ne décide rien.
            --}}
            <div class="mb-6 overflow-hidden rounded-lg border border-warning/30 bg-warning/5">
                <div class="border-b border-warning/30 px-4 py-3">
                    <h2 class="font-display text-base font-medium text-foreground">{{ __('baobab::admin.mails.drift_title') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ __('baobab::admin.mails.drift_intro') }}</p>
                </div>

                <div class="space-y-4 p-4">
                    <div>
                        <h3 class="mb-1 text-xs font-medium uppercase text-muted">{{ __('baobab::admin.mails.drift_subject') }}</h3>
                        @if (! $drift->subjectChanged())
                            <p class="text-sm text-muted">{{ __('baobab::admin.mails.drift_unchanged') }}</p>
                        @elseif ($subjectDiff === null)
                            <p class="text-sm text-muted">{{ __('baobab::admin.mails.drift_too_large') }}</p>
                        @else
                            <pre class="overflow-x-auto rounded bg-surface-subtle p-3 font-mono text-sm leading-normal">@foreach ($subjectDiff as $line)<span @class([
                                'block',
                                'text-danger' => $line['type'] === 'removed',
                                'text-success' => $line['type'] === 'added',
                                'text-muted' => $line['type'] === 'kept',
                            ])>{{ $line['marker'] }} {{ $line['line'] }}</span>@endforeach</pre>
                        @endif
                    </div>

                    <div>
                        <h3 class="mb-1 text-xs font-medium uppercase text-muted">{{ __('baobab::admin.mails.drift_body') }}</h3>
                        @if (! $drift->bodyChanged())
                            <p class="text-sm text-muted">{{ __('baobab::admin.mails.drift_unchanged') }}</p>
                        @elseif ($bodyDiff === null)
                            <p class="text-sm text-muted">{{ __('baobab::admin.mails.drift_too_large') }}</p>
                        @else
                            <pre class="overflow-x-auto rounded bg-surface-subtle p-3 font-mono text-sm leading-normal">@foreach ($bodyDiff as $line)<span @class([
                                'block',
                                'text-danger' => $line['type'] === 'removed',
                                'text-success' => $line['type'] === 'added',
                                'text-muted' => $line['type'] === 'kept',
                            ])>{{ $line['marker'] }} {{ $line['line'] }}</span>@endforeach</pre>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-baobab::form method="POST" :action="route('admin.mails.update', $declaration->key)">
                    <section class="mb-6 rounded-lg border border-border bg-surface p-4">
                        <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.mails.section_content') }}</h2>
                        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.mails.section_content_hint') }}</p>

                        <x-baobab::field.text
                            name="subject"
                            :label="__('baobab::admin.mails.subject')"
                            :value="$template->subject"
                        />

                        <x-baobab::field.richtext
                            name="body"
                            :label="__('baobab::admin.mails.body')"
                            :value="$template->body"
                            :variables="$variables"
                        />
                    </section>

                    <section class="mb-6 rounded-lg border border-border bg-surface p-4">
                        <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.mails.section_sender') }}</h2>
                        <p class="mb-4 text-sm text-muted">{{ __('baobab::admin.mails.section_sender_hint') }}</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-baobab::field.text
                                name="from_address"
                                type="email"
                                :label="__('baobab::admin.mails.from_address')"
                                :value="$template->fromAddress"
                            />

                            <x-baobab::field.text
                                name="from_name"
                                :label="__('baobab::admin.mails.from_name')"
                                :value="$template->fromName"
                            />
                        </div>

                        <p class="text-xs text-muted">{{ __('baobab::admin.mails.from_global', ['sender' => $globalSender]) }}</p>
                    </section>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-baobab::button type="submit" variant="primary">
                            {{ __('baobab::admin.mails.save') }}
                        </x-baobab::button>

                        {{--
                            L'aperçu poste le formulaire courant vers une autre
                            route, dans un nouvel onglet : on prévisualise ce
                            qu'on vient d'écrire, et le document rendu est
                            l'e-mail entier, pas une vignette encastrée dans
                            l'admin.
                        --}}
                        <button
                            type="submit"
                            formaction="{{ route('admin.mails.preview', $declaration->key) }}"
                            formmethod="POST"
                            formtarget="_blank"
                            class="rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-surface-subtle"
                        >
                            {{ __('baobab::admin.mails.preview') }}
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-muted">{{ __('baobab::admin.mails.preview_hint') }}</p>
                </x-baobab::form>

                {{--
                    Hors du formulaire d'édition : restaurer et tester ne sont
                    pas des variantes d'« enregistrer », et les imbriquer
                    donnerait des formulaires dans un formulaire.
                --}}
                @if ($template->customised)
                    <x-baobab::form method="POST" :action="route('admin.mails.restore', $declaration->key)" class="mt-6">
                        <x-baobab::button type="submit" variant="ghost">
                            {{ __('baobab::admin.mails.restore') }}
                        </x-baobab::button>
                        <p class="mt-1 text-xs text-muted">{{ __('baobab::admin.mails.restore_confirm') }}</p>
                    </x-baobab::form>
                @endif
            </div>

            <div class="space-y-6">
                <section class="rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.mails.section_variables') }}</h2>
                    <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.mails.section_variables_hint') }}</p>

                    <ul class="space-y-2 text-sm">
                        @foreach ($variables as $variableName => $variableLabel)
                            <li>
                                <code class="rounded bg-surface-subtle px-1 py-0.5 font-mono text-xs text-foreground">&lbrace;&lbrace; {{ $variableName }} &rbrace;&rbrace;</code>
                                @if (in_array($variableName, $required, true))
                                    <x-baobab::badge variant="warning" class="ml-1">
                                        {{ __('baobab::admin.mails.required_badge') }}
                                    </x-baobab::badge>
                                @endif
                                <span class="mt-0.5 block text-xs text-muted">{{ $variableLabel }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-1 font-display text-base font-medium text-foreground">{{ __('baobab::admin.mails.test_legend') }}</h2>
                    <p class="mb-3 text-sm text-muted">{{ __('baobab::admin.mails.test_hint') }}</p>

                    <x-baobab::form method="POST" :action="route('admin.mails.test', $declaration->key)">
                        <x-baobab::field.text
                            name="recipient"
                            type="email"
                            :label="__('baobab::admin.mails.test_recipient')"
                        />

                        <x-baobab::button type="submit" variant="ghost">
                            {{ __('baobab::admin.mails.test_send') }}
                        </x-baobab::button>
                    </x-baobab::form>
                </section>
            </div>
        </div>
    </x-baobab::page>
@endsection
