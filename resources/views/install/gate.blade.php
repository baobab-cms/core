@extends('baobab::install.layout')

@section('title', 'Code d\'installation')
@section('subtitle', 'Code d\'installation')

@section('content')
    {{--
        Le chemin du fichier est affiché, jamais son contenu (§6.2 : « aucune
        donnée sensible renvoyée au navigateur »). Dire où chercher n'apprend
        rien à qui n'a pas accès au serveur, et évite un ticket de support à
        tous ceux qui l'ont.
    --}}
    <p class="lede">
        Pour prouver que ce site est bien le vôtre, saisissez le code qui se
        trouve sur le serveur.
    </p>

    <p class="hint">
        Il a été écrit dans&nbsp;:
        <code>{{ $tokenPath }}</code>
    </p>

    @if ($errors->any())
        <p class="alert" role="alert">{{ $errors->first('token') }}</p>
    @endif

    <form method="POST" action="{{ route('baobab.install.unlock') }}" class="form">
        @csrf

        <label class="label" for="token">Code d'installation</label>
        <input
            class="input"
            id="token"
            name="token"
            type="text"
            inputmode="latin"
            autocomplete="off"
            autocapitalize="off"
            spellcheck="false"
            autofocus
            required
        >

        <button class="button" type="submit">Continuer</button>
    </form>
@endsection
