@extends('baobab::install.layout')

@section('title', 'Base de données')
@section('subtitle', 'Base de données')

@section('content')
    @include('baobab::install.partials.steps')

    <p class="lede">
        Ces informations vous ont été données par votre hébergeur. Rien n'est
        écrit tant que vous n'avez pas lancé l'installation.
    </p>

    @if ($errors->any())
        <p class="alert" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('baobab.install.database') }}" class="form">
        @csrf

        <label class="label" for="driver">Type de base</label>
        {{--
            SQLite n'apparaît qu'en local et en préproduction — §8 point 3 :
            interdit en production. L'option est **absente**, pas grisée : une
            option grisée invite à chercher comment la forcer.
        --}}
        <select class="input" id="driver" name="driver" required>
            @foreach ($drivers as $value => $label)
                <option value="{{ $value }}" @selected(($values['driver'] ?? 'mysql') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <label class="label" for="database">Nom de la base</label>
        <input class="input" id="database" name="database" type="text" value="{{ old('database', $values['database'] ?? '') }}" required autocomplete="off">

        <div class="field-row">
            <div class="field-row__main">
                <label class="label" for="host">Hôte</label>
                <input class="input" id="host" name="host" type="text" value="{{ old('host', $values['host'] ?? '127.0.0.1') }}" autocomplete="off">
            </div>
            <div class="field-row__side">
                <label class="label" for="port">Port</label>
                <input class="input" id="port" name="port" type="number" value="{{ old('port', $values['port'] ?? '') }}" placeholder="3306" autocomplete="off">
            </div>
        </div>

        <label class="label" for="username">Identifiant</label>
        <input class="input" id="username" name="username" type="text" value="{{ old('username', $values['username'] ?? '') }}" autocomplete="off">

        <label class="label" for="password">Mot de passe</label>
        <input class="input" id="password" name="password" type="password" value="{{ $values['password'] ?? '' }}" autocomplete="off">

        <label class="label" for="prefix">Préfixe des tables <span class="label__hint">facultatif</span></label>
        <input class="input" id="prefix" name="prefix" type="text" value="{{ old('prefix', $values['prefix'] ?? '') }}" placeholder="bb_" autocomplete="off">
        <p class="hint">
            Utile si cette base héberge déjà autre chose. Lettres minuscules,
            chiffres et tirets bas.
        </p>

        <div class="actions">
            <a class="button button--ghost" href="{{ route('baobab.install.index') }}">Retour</a>
            <button class="button" type="submit">Continuer</button>
        </div>
    </form>
@endsection
