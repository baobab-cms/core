@extends('baobab::install.layout')

@section('title', 'Installation en cours')
@section('subtitle', 'Installation')

@section('content')
    {{--
        Chaque étape est sa propre requête (§6.1, n° 211) : `RunMigrations` est
        l'étape longue, et une requête unique portant toute la séquence se
        ferait tuer par les limites d'un mutualisé. La contrainte technique et
        l'exigence esthétique disent ici la même chose — l'avancement affiché
        est l'avancement **réel**, puisqu'il n'existe qu'à mesure que les
        requêtes reviennent.

        La liste est rendue côté serveur : sans JavaScript, on voit au moins ce
        qui va se passer, et le bouton de repli permet d'avancer à la main.
    --}}
    <p class="lede">Baobab installe votre site. Ne fermez pas cette page.</p>

    <ol class="run" id="run" data-endpoint="{{ route('baobab.install.step') }}" data-token="{{ csrf_token() }}">
        @foreach ($steps as $step)
            <li class="run__step" data-step="{{ $step['key'] }}">
                <span class="run__tile" aria-hidden="true"></span>
                <span class="run__label">{{ $step['label'] }}</span>
                <span class="run__state" data-role="state">en attente</span>
                <ul class="run__details" data-role="details"></ul>
            </li>
        @endforeach
    </ol>

    <p class="alert" id="run-error" role="alert" hidden></p>

    <div class="actions" id="run-actions">
        <noscript>
            <p class="hint">
                Le suivi automatique demande JavaScript. Utilisez le bouton
                ci-dessous&nbsp;: chaque clic exécute une étape.
            </p>
        </noscript>

        <form method="POST" action="{{ route('baobab.install.step') }}" id="run-fallback">
            @csrf
            <button class="button" type="submit">Exécuter l'étape suivante</button>
        </form>
    </div>

    <script src="{{ url('baobab/install/wizard.js') }}?v={{ $assetVersion }}" defer></script>
@endsection
