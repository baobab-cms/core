@extends('baobab::install.layout')

@section('title', 'Trop de tentatives')
@section('subtitle', 'Trop de tentatives')

@section('content')
    {{--
        Un 429 nu au milieu d'une installation est le pire moment pour laisser
        quelqu'un sans explication : il vient de saisir un code plusieurs fois,
        il ne sait pas s'il a cassé quelque chose, et rien ne lui dit quoi faire.
        Trois informations lui manquent, et cet écran les donne — ce qui s'est
        passé, combien de temps attendre, où retrouver le code.

        On ne dit **pas** combien de tentatives restaient ni combien ont échoué :
        ce serait renseigner qui tâtonne, sans aider celui qui a le fichier sous
        les yeux.
    --}}
    <p class="alert" role="alert">
        Trop de tentatives en peu de temps. L'accès est suspendu
        <strong>{{ $seconds }}&nbsp;seconde{{ $seconds > 1 ? 's' : '' }}</strong>.
    </p>

    <p class="lede">
        Cette limite protège l'installation&nbsp;: tant que le site n'est pas
        installé, n'importe qui pourrait tenter de deviner le code. Ce n'est pas
        une panne, et rien n'est perdu.
    </p>

    <p class="hint">
        Le code exact se trouve dans&nbsp;:
        <code>{{ $tokenPath }}</code>
    </p>

    <p class="hint">
        Passé ce délai, revenez à la
        <a href="{{ route('baobab.install.gate') }}">page d'installation</a>.
    </p>
@endsection
