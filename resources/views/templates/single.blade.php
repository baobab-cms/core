{{--
    Repli de détail d'un contenu (spec 19 §6).

    Ne rend que le titre, à dessein. Les données passées par
    `RenderContentEntry` sont `entry` et `title` : rendre les champs
    exigerait le blueprint du Content Type, donc d'élargir la charge utile du
    filtre public `baobab.render.data`. Décision du 11 août 2026 : hors
    périmètre de cette passe, qui donne aux replis leur **forme** et non leur
    **contenu** — un repli n'est jamais un substitut de thème (§6.1). Question
    consignée au suivi.
--}}
@extends('baobab::layouts.public')

@section('content')
    <article>
        <h1 class="bb-title">{{ $title }}</h1>
    </article>
@endsection
