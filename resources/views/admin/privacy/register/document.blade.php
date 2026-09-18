<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('baobab::admin.privacy_register.document_title') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; color: #1a1a1a; max-width: 52rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        h1 { font-size: 1.6rem; }
        h2 { font-size: 1.15rem; margin-top: 2rem; border-bottom: 1px solid #ccc; padding-bottom: .25rem; }
        dt { font-weight: 600; margin-top: .75rem; }
        dd { margin: 0; }
        .meta { color: #555; font-size: .9rem; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>{{ __('baobab::admin.privacy_register.document_title') }}</h1>
    <p class="meta">{{ __('baobab::admin.privacy_register.document_generated', ['date' => $generatedAt->format('Y-m-d H:i')]) }}</p>
    <p class="meta">{{ __('baobab::admin.privacy_register.document_disclaimer') }}</p>

    @foreach ($register->declarations as $key => $declaration)
        <h2>{{ $declaration->title }} <small>({{ $key }})</small></h2>
        <dl>
            <dt>{{ __('baobab::admin.privacy_register.nature') }}</dt>
            <dd>{{ $declaration->nature }}</dd>
            <dt>{{ __('baobab::admin.privacy_register.purpose') }}</dt>
            <dd>{{ $declaration->purpose }}</dd>
            <dt>{{ __('baobab::admin.privacy_register.legal_basis') }}</dt>
            <dd>{{ $declaration->legalBasis }}</dd>
            <dt>{{ __('baobab::admin.privacy_register.retention') }}</dt>
            <dd>{{ $declaration->retention }}</dd>
            <dt>{{ __('baobab::admin.privacy_register.external_services') }}</dt>
            <dd>{{ $declaration->externalServices === [] ? __('baobab::admin.privacy_register.none') : implode(', ', $declaration->externalServices) }}</dd>
        </dl>
    @endforeach

    <h2>{{ __('baobab::admin.privacy_register.recipients_title') }}</h2>
    @if ($register->recipients === [])
        <p>{{ __('baobab::admin.privacy_register.no_recipients') }}</p>
    @else
        <ul>
            @foreach ($register->recipients as $recipient)
                <li>{{ $recipient }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
