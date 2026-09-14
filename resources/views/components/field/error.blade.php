@props(['name'])

{{--
    Partagé par toute la famille `field/*` (suivi n° 307 constat 3, n° 311) :
    l'`id` généré ici (`{name}-error`) est le seul contrat entre ce message et
    l'`aria-describedby` posé par chaque champ sur son contrôle — un seul
    endroit qui décide du format de l'id, jamais dupliqué.
--}}
@error($name)
    <p id="{{ $name }}-error" class="mt-1 text-xs text-danger">{{ $message }}</p>
@enderror
