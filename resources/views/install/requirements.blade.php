@extends('baobab::install.layout')

@section('title', 'Prérequis')
@section('subtitle', 'Ce que votre hébergement propose')

@section('content')
    @include('baobab::install.partials.steps')

    {{--
        §4.1 — « détecter, jamais niveler ». Cet écran dit ce qui est, y compris
        ce qui manque sans empêcher d'installer : une capacité absente se
        contourne, et l'utilisateur d'un mutualisé n'a souvent aucun moyen de
        l'obtenir. Le taire lui ferait chercher plus tard pourquoi son site se
        comporte autrement que la documentation.
    --}}
    <ul class="checks">
        @foreach ($report->requirements as $requirement)
            <li class="check @if ($requirement->satisfied) is-ok @elseif ($requirement->blocking) is-blocking @else is-advisory @endif">
                <span class="check__label">{{ $requirement->label }}</span>
                @if ($requirement->detail)
                    <span class="check__detail">{{ $requirement->detail }}</span>
                @endif
                @if (! $requirement->satisfied && $requirement->remedy)
                    <span class="check__remedy">{{ $requirement->remedy }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($blocking !== [])
        <p class="alert" role="alert">
            {{ count($blocking) }} condition{{ count($blocking) > 1 ? 's' : '' }} nécessaire{{ count($blocking) > 1 ? 's' : '' }}
            manque{{ count($blocking) > 1 ? 'nt' : '' }}. Corrigez-les puis rechargez cette page&nbsp;: rien n'a été modifié.
        </p>

        <form method="GET" action="{{ route('baobab.install.index') }}" class="form">
            <button class="button" type="submit">Vérifier à nouveau</button>
        </form>
    @else
        <p class="lede">Tout ce qui est nécessaire est là. L'installation peut commencer.</p>

        <p class="actions">
            <a class="button button--link" href="{{ route('baobab.install.database') }}">Commencer</a>
        </p>
    @endif
@endsection
