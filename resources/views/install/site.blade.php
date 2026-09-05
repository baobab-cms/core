@extends('baobab::install.layout')

@section('title', 'Votre site')
@section('subtitle', 'Votre site')

@section('content')
    @include('baobab::install.partials.steps')

    <p class="lede">Dernier écran avant l'installation. Tout reste modifiable ensuite depuis l'administration.</p>

    @if ($errors->any())
        <p class="alert" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('baobab.install.site') }}" class="form">
        @csrf

        <label class="label" for="name">Nom du site</label>
        <input class="input" id="name" name="name" type="text" value="{{ old('name', $values['name'] ?? '') }}" required>

        <label class="label" for="url">Adresse du site</label>
        <input class="input" id="url" name="url" type="url" value="{{ old('url', $defaultUrl) }}" required>
        <p class="hint">Avec <code>https://</code>, et sans barre oblique finale.</p>

        <label class="label" for="timezone">Fuseau horaire</label>
        <select class="input" id="timezone" name="timezone" required>
            @foreach ($timezones as $timezone)
                <option value="{{ $timezone }}" @selected(($values['timezone'] ?? 'Europe/Paris') === $timezone)>{{ $timezone }}</option>
            @endforeach
        </select>

        <label class="choice">
            <input type="checkbox" name="registration_open" value="1" @checked($values['registration_open'] ?? false)>
            <span>Autoriser les visiteurs à créer un compte</span>
        </label>

        {{--
            Télémétrie — §8 point 2 : opt-in **explicite**, décochée par défaut.
            Ce qui serait transmis est dit ici en toutes lettres : une case dont
            on ignore le contenu n'est pas un consentement.
        --}}
        <label class="choice">
            <input type="checkbox" name="telemetry" value="1" @checked($values['telemetry'] ?? false)>
            <span>
                Transmettre des statistiques anonymes
                <span class="choice__hint">
                    Version de Baobab, version de PHP, type de base, mode
                    d'installation. Aucun identifiant, aucune adresse, aucun
                    contenu. Modifiable à tout moment.
                </span>
            </span>
        </label>

        {{--
            Contenu de démonstration (§8 point 1) : décoché par défaut, même
            motif que la télémétrie — une case ignorée ne doit jamais valoir
            un « oui » silencieux. Retirable à tout moment depuis l'admin.
        --}}
        <label class="choice">
            <input type="checkbox" name="demo_content" value="1" @checked($values['demo_content'] ?? false)>
            <span>
                Installer un contenu de démonstration
                <span class="choice__hint">
                    Deux pages, trois articles et un menu, pour voir le site
                    habillé tout de suite. Retirable en un geste depuis
                    l'administration.
                </span>
            </span>
        </label>

        <div class="actions">
            <a class="button button--ghost" href="{{ route('baobab.install.account') }}">Retour</a>
            <button class="button" type="submit">Installer</button>
        </div>
    </form>
@endsection
