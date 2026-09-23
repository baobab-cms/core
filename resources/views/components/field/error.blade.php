@props(['name', 'bag' => 'default', 'id' => null])

{{--
    Partagé par toute la famille `field/*` (suivi n° 307 constat 3, n° 311) :
    l'`id` généré ici (`{id}-error`, l'id du champ valant son nom par défaut)
    est le seul contrat entre ce message et l'`aria-describedby` posé par
    chaque champ sur son contrôle — un seul endroit qui décide du format de
    l'id, jamais dupliqué.

    `bag` : sac d'erreurs nommé, pour deux formulaires d'un même écran qui
    partagent un nom de champ (spec 04 §9, décision 7 : `current_password`
    sur Mon compte → Sécurité).
--}}
@error($name, $bag)
    <p id="{{ $id ?? $name }}-error" class="mt-1 text-xs text-danger">{{ $message }}</p>
@enderror
