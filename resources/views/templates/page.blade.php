{{--
    Repli de page statique (spec 19 §6). Même remarque que `single` sur le
    contenu : la forme, pas les champs.
--}}
@extends('baobab::layouts.public')

@section('content')
    <article>
        <h1 class="bb-title">{{ $title }}</h1>
    </article>
@endsection
