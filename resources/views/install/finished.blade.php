@extends('baobab::install.layout')

@section('title', 'Installation terminée')
@section('subtitle', 'C\'est installé')

@section('content')
    {{--
        Cet écran est rendu par la requête **qui vient d'écrire le lock**, et
        c'est essentiel : dès le chargement suivant, `/install/*` n'existe plus
        (§6.3). Toute page de fin servie par une requête ultérieure donnerait un
        404 au moment le plus triomphal.

        La Pass C3 remplacera cet écran par la checklist du §7 — cron, worker,
        configuration du serveur web — gouvernée par le profil d'hébergement.
        Ce qui suit est le strict nécessaire pour ne pas laisser l'utilisateur
        sans porte de sortie.
    --}}
    <p class="lede">Votre site est installé. L'installateur vient de se refermer&nbsp;: cette adresse ne répondra plus.</p>

    <p class="actions">
        <a class="button button--link" href="{{ $adminUrl }}">Aller à l'administration</a>
    </p>

    <p class="hint">
        Connectez-vous avec l'adresse e-mail et le mot de passe que vous venez
        de choisir. Il reste quelques réglages serveur à faire — ils vous seront
        présentés dans l'administration.
    </p>
@endsection
