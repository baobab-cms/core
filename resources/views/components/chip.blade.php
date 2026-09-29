{{--
    Niveau « Chip » de la signature (`direction-visuelle.md` §6.2) : un
    artefact réel — retrouvable par `grep` dans le code, la base ou le
    système de fichiers (§6.1) — cité dans une phrase ou une cellule. Mono
    `sm` réduit à 0,92em (même traitement que le sous-titre mono de
    `<x-baobab::page>`), fond `sand` clair, rayon `md`, padding `xs`.

    `value` sert à la fois d'affichage et de source copiée : une seule
    vérité, pas de doublon texte/attribut à désynchroniser. Copie au clic
    (§6.2 : « tout chip est copiable, et le dit ») — le verbe visible passe
    de l'artefact lui-même à « Copié » pendant 2 s, transition 120 ms (même
    durée que le survol de ligne de tableau, Pass C, suivi n° 371).
--}}
@props(['value'])

<button
    type="button"
    x-data="{ copied: false }"
    x-on:click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 2000)"
    {{ $attributes->class([
        'inline-flex items-center rounded-md bg-sand-100 px-1.5 py-0.5 font-mono text-[0.92em] text-sand-700 transition-colors duration-[120ms] hover:bg-sand-200',
    ]) }}
>
    <span x-show="!copied">{{ $value }}</span>
    <span x-show="copied" x-cloak>{{ __('baobab::admin.components.copied') }}</span>
</button>
