<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('baobab::privacy.export.title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #1a1a1a; max-width: 52rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        h1 { font-size: 1.6rem; }
        h2 { font-size: 1.15rem; margin-top: 2rem; border-bottom: 1px solid #ccc; padding-bottom: .25rem; }
        dt { font-weight: 600; margin-top: .5rem; word-break: break-word; }
        dd { margin: 0; word-break: break-word; }
        .meta { color: #555; font-size: .9rem; }
    </style>
</head>
<body>
    <h1>{{ __('baobab::privacy.export.title') }}</h1>
    <p class="meta">{{ __('baobab::privacy.export.generated', ['date' => $generatedAt->format('Y-m-d H:i')]) }}</p>

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }} <small>({{ $section['key'] }})</small></h2>
        <dl>
            @foreach ($section['rows'] as $row)
                <dt>{{ $row['label'] }}</dt>
                <dd>{{ $row['value'] }}</dd>
            @endforeach
        </dl>
        @if ($section['files'] !== [])
            <p>{{ __('baobab::privacy.export.files') }}</p>
            <ul>
                @foreach ($section['files'] as $file)
                    <li><a href="{{ rawurlencode($section['key']) }}/files/{{ rawurlencode($file) }}">{{ $file }}</a></li>
                @endforeach
            </ul>
        @endif
    @endforeach

    @if ($unsupported !== [])
        <h2>{{ __('baobab::privacy.export.unsupported_title') }}</h2>
        <p>{{ __('baobab::privacy.export.unsupported_hint') }}</p>
        <ul>
            @foreach ($unsupported as $key)
                <li>{{ $key }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
