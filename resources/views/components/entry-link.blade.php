{{--
    Titre d'une entrée, lié si son type est adressable (spec 19 §5.5).

    Un type non adressable n'a pas de page : son titre s'affiche sans lien
    plutôt que de mener à une 404. Le slot, s'il est fourni, remplace le titre
    résolu depuis le blueprint.
--}}
@if ($url !== null)
    <a href="{{ $url }}" {{ $attributes }}>{{ $slot->isEmpty() ? $title : $slot }}</a>
@else
    <span {{ $attributes }}>{{ $slot->isEmpty() ? $title : $slot }}</span>
@endif
