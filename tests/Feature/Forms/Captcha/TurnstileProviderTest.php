<?php

use Baobab\Forms\Captcha\TurnstileProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('returns true when Cloudflare confirms the token', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true]),
    ]);

    expect((new TurnstileProvider)->verify('good-token', 'secret', '203.0.113.1'))->toBeTrue();

    Http::assertSent(fn ($request): bool => $request['secret'] === 'secret'
        && $request['response'] === 'good-token'
        && $request['remoteip'] === '203.0.113.1');
});

it('returns false when Cloudflare rejects the token', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
    ]);

    expect((new TurnstileProvider)->verify('bad-token', 'secret'))->toBeFalse();
});

it('returns false on an HTTP error response rather than trusting a malformed body', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(null, 500),
    ]);

    expect((new TurnstileProvider)->verify('token', 'secret'))->toBeFalse();
});

it('returns false on a network failure rather than letting the exception escape', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => fn () => throw new ConnectionException('timed out'),
    ]);

    expect((new TurnstileProvider)->verify('token', 'secret'))->toBeFalse();
});
