<?php

use Baobab\Forms\Captcha\HcaptchaProvider;
use Illuminate\Support\Facades\Http;

it('returns true when hCaptcha confirms the token', function () {
    Http::fake([
        'https://hcaptcha.com/siteverify' => Http::response(['success' => true]),
    ]);

    expect((new HcaptchaProvider)->verify('good-token', 'secret', '203.0.113.1'))->toBeTrue();

    Http::assertSent(fn ($request): bool => $request['secret'] === 'secret'
        && $request['response'] === 'good-token'
        && $request['remoteip'] === '203.0.113.1');
});

it('returns false when hCaptcha rejects the token', function () {
    Http::fake([
        'https://hcaptcha.com/siteverify' => Http::response(['success' => false]),
    ]);

    expect((new HcaptchaProvider)->verify('bad-token', 'secret'))->toBeFalse();
});

it('returns false on an HTTP error response rather than trusting a malformed body', function () {
    Http::fake([
        'https://hcaptcha.com/siteverify' => Http::response(null, 500),
    ]);

    expect((new HcaptchaProvider)->verify('token', 'secret'))->toBeFalse();
});
