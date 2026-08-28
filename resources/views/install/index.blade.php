@extends('baobab::install.layout')

@section('title', 'Installation')
@section('subtitle', 'Prêt à installer')

@section('content')
    {{--
        Coquille de la Pass C1. Les étapes du §4 arrivent en Pass C2, chacune
        dans sa propre requête (n° 211), branchées sur `InstallationPipeline`
        — le même orchestrateur que `baobab:install`, pour que les deux
        surfaces restent deux adaptateurs d'une seule séquence (n° 216).
    --}}
    <p class="lede">
        Le socle d'installation est en place&nbsp;: ce navigateur détient la
        session, et il est le seul.
    </p>

    <p class="hint">
        Les étapes d'installation arrivent avec la passe suivante. En attendant,
        <code>php artisan baobab:install</code> mène l'installation complète en
        console.
    </p>
@endsection
