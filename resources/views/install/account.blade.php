@extends('baobab::install.layout')

@section('title', 'Compte administrateur')
@section('subtitle', 'Votre compte')

@section('content')
    @include('baobab::install.partials.steps')

    <p class="lede">
        Ce compte sera le Super Admin du site&nbsp;: il peut tout, y compris se
        retirer ses propres droits. Choisissez un mot de passe que vous ne
        réutilisez nulle part ailleurs.
    </p>

    @if ($errors->any())
        <p class="alert" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('baobab.install.account') }}" class="form">
        @csrf

        <label class="label" for="name">Nom <span class="label__hint">facultatif</span></label>
        <input class="input" id="name" name="name" type="text" value="{{ old('name', $values['name'] ?? '') }}" autocomplete="name">

        <label class="label" for="email">Adresse e-mail</label>
        <input class="input" id="email" name="email" type="email" value="{{ old('email', $values['email'] ?? '') }}" required autocomplete="username">

        <label class="label" for="password">Mot de passe</label>
        <input class="input" id="password" name="password" type="password" required autocomplete="new-password" minlength="12">
        {{--
            Douze caractères, et aucune règle de composition : les règles de
            composition produisent des mots de passe courts et prévisibles,
            quand la longueur est la seule contrainte qui augmente réellement
            le coût d'une attaque.
        --}}
        <p class="hint">Douze caractères au minimum. Une phrase fait un excellent mot de passe.</p>

        <label class="label" for="password_confirmation">Confirmation</label>
        <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <div class="actions">
            <a class="button button--ghost" href="{{ route('baobab.install.database') }}">Retour</a>
            <button class="button" type="submit">Continuer</button>
        </div>
    </form>
@endsection
