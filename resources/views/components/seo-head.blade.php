{{-- <x-baobab::seo-head /> (spec 03 §3, spec 07 §6) — balises composées par
     Baobab\View\Components\Seo depuis SeoContext, jamais recalculées ici. --}}
<title>{{ $title }}</title>

@if ($description)
    <meta name="description" content="{{ $description }}">
@endif

@if ($robotsNoindex || $robotsNofollow)
    <meta name="robots" content="{{ implode(',', array_filter([$robotsNoindex ? 'noindex' : 'index', $robotsNofollow ? 'nofollow' : 'follow'])) }}">
@endif

@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif

<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
@if ($ogDescription)
    <meta property="og:description" content="{{ $ogDescription }}">
@endif
@if ($ogImageUrl)
    <meta property="og:image" content="{{ $ogImageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $ogTitle }}">
@if ($ogDescription)
    <meta name="twitter:description" content="{{ $ogDescription }}">
@endif
@if (! empty($jsonld))
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $jsonld], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
