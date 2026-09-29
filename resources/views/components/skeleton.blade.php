{{--
    Squelette de chargement (`direction-visuelle.md` §9) : forme du contenu
    attendu, teinte `sand`, **statique** — un balayage animé est une
    animation ambiante interdite (§5.4). Purement présentationnel
    (`aria-hidden`) : le conteneur qui l'affiche porte `aria-busy` là où
    c'est pertinent (ex. `media-picker.blade.php`), pas ce composant.
--}}
<div {{ $attributes->class(['rounded-md bg-sand-100']) }} aria-hidden="true"></div>
