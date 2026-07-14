<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: Arial, Helvetica, sans-serif; color: #0f172a; }
        .baobab-email-wrapper { width: 100%; background-color: #f8fafc; padding: 24px 0; }
        .baobab-email-card { width: 600px; max-width: 100%; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; }
        .baobab-email-header { padding: 24px; border-bottom: 3px solid {{ $branding->primary_color ?? '#0f766e' }}; }
        .baobab-email-brand { font-size: 18px; font-weight: bold; color: {{ $branding->primary_color ?? '#0f766e' }}; text-decoration: none; }
        .baobab-email-body { padding: 24px; font-size: 14px; line-height: 1.6; }
        .baobab-email-footer { padding: 16px 24px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <table role="presentation" class="baobab-email-wrapper" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" class="baobab-email-card" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="baobab-email-header">
                            @if ($branding->logo)
                                <img src="{{ $branding->logo->url() }}" alt="{{ config('app.name', 'Baobab') }}" height="32">
                            @else
                                <span class="baobab-email-brand">{{ config('app.name', 'Baobab') }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="baobab-email-body">
                            {!! $body !!}
                        </td>
                    </tr>
                    <tr>
                        <td class="baobab-email-footer">
                            {{ config('app.name', 'Baobab') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
