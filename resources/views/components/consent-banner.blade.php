{{--
    Bannière de consentement (spec 16 §3.2) : la vue de bannière — Core ou
    surcharge `partials/cookie-banner` du thème — puis le script, qui lit sa
    configuration sur ses propres attributs. Le composant ne rend rien du
    tout quand aucune catégorie ne demande de consentement (décision 21).
--}}
@include($bannerView, ['categories' => $categories, 'necessary' => $necessary])

<script
    src="{{ $scriptUrl }}"
    defer
    data-baobab-consent-config
    data-categories="{{ $categoryKeys }}"
    data-fingerprint="{{ $fingerprint }}"
    data-lifetime="{{ $lifetime }}"
></script>
