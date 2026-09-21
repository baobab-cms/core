{{--
    Repli du portail RGPD public (spec 16 §4, spec 03 §4 : `privacy` → `index`).

    Un seul template, plusieurs états — `$state` vaut :
    - `form` : saisie de l'adresse ;
    - `sent` : réponse à la saisie, identique que l'adresse soit connue ou non ;
    - `confirm` : page ouverte par le lien du mail (`$confirmUrl`) — le GET
      n'agit jamais, le bouton envoie un POST ;
    - `confirmed` : demande enregistrée ;
    - `invalid` / `used` : lien illisible, expiré ou déjà consommé ;
    - `delivery` : remise de l'archive (`$downloadUrl`, `$revealUrl`,
      `$password`, `$hasPassword`, `$expiresAt`) ;
    - `gone` : archive échue.

    `$renderToken` est fourni dans tous les états : à poser tel quel dans le
    champ `_form_rt` du formulaire de saisie (piège temporel).

    Un thème qui surcharge ce template doit traiter tous ces états. Ton sobre
    et factuel (spec 19 §5.8) : ce qui se passe, ce qu'on attend de la personne.
--}}
@extends('baobab::layouts.public')

@section('content')
    <h1 class="bb-title">{{ __('baobab::privacy.portal.title') }}</h1>

    @switch($state)
        @case('sent')
            <p role="status">{{ __('baobab::privacy.portal.sent') }}</p>
            <p class="bb-muted">{{ __('baobab::privacy.portal.sent_help') }}</p>
            @break

        @case('confirm')
            <p>{{ __('baobab::privacy.portal.confirm_intro') }}</p>
            <form method="POST" action="{{ $confirmUrl }}">
                @csrf
                <button type="submit">{{ __('baobab::privacy.portal.confirm_submit') }}</button>
            </form>
            @break

        @case('confirmed')
            <p role="status">{{ __('baobab::privacy.portal.confirmed') }}</p>
            <p class="bb-muted">{{ __('baobab::privacy.portal.confirmed_help') }}</p>
            @break

        @case('invalid')
        @case('used')
            <p role="alert">{{ __('baobab::privacy.portal.state_'.$state) }}</p>
            <p><a href="{{ route('baobab.privacy.portal') }}">{{ __('baobab::privacy.portal.restart') }}</a></p>
            @break

        @case('gone')
            <p role="alert">{{ __('baobab::privacy.portal.gone') }}</p>
            <p><a href="{{ route('baobab.privacy.portal') }}">{{ __('baobab::privacy.portal.restart') }}</a></p>
            @break

        @case('delivery')
            <p>{{ __('baobab::privacy.portal.delivery_intro', ['date' => $expiresAt]) }}</p>
            <p><a href="{{ $downloadUrl }}">{{ __('baobab::privacy.portal.download') }}</a></p>

            @if ($password)
                <div role="alert">
                    <p>{{ __('baobab::privacy.portal.password_label') }}</p>
                    <p><code>{{ $password }}</code></p>
                    <p class="bb-muted">{{ __('baobab::privacy.portal.password_once') }}</p>
                </div>
            @elseif ($hasPassword)
                <p>{{ __('baobab::privacy.portal.password_help') }}</p>
                <form method="POST" action="{{ $revealUrl }}">
                    @csrf
                    <button type="submit">{{ __('baobab::privacy.portal.password_reveal') }}</button>
                </form>
            @else
                <p class="bb-muted">{{ __('baobab::privacy.portal.password_gone') }}</p>
            @endif
            @break

        @default
            <p>{{ __('baobab::privacy.portal.intro') }}</p>

            <form method="POST" action="{{ route('baobab.privacy.portal.request') }}">
                @csrf

                {{-- Honeypot et piège temporel (spec 14 §7.1) : jamais `type="hidden"`, hors écran, hors tabulation. --}}
                <div style="position:absolute; left:-9999px" aria-hidden="true">
                    <input type="text" name="{{ \Baobab\Forms\Support\FormSpamGuard::HONEYPOT_FIELD }}" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="{{ \Baobab\Forms\Support\FormSpamGuard::TIMESTAMP_FIELD }}" value="{{ $renderToken }}">

                <p>
                    <label for="bb-privacy-email">{{ __('baobab::privacy.portal.email_label') }}</label><br>
                    <input id="bb-privacy-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                        @error('email') aria-invalid="true" aria-describedby="bb-privacy-email-error" @enderror>
                </p>
                @error('email')
                    <p id="bb-privacy-email-error" role="alert">{{ $message }}</p>
                @enderror

                <button type="submit">{{ __('baobab::privacy.portal.submit') }}</button>
            </form>
    @endswitch
@endsection
