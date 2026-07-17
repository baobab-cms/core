{{-- HTML personnalisé — sortie volontairement non nettoyée, spec 10 §4 décision 3 (voir CustomHtmlWidget). --}}
@if ($data['html'] !== '')
    {!! $data['html'] !!}
@endif
