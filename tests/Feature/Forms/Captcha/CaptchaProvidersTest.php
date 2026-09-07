<?php

use Baobab\Facades\Hook;
use Baobab\Forms\Captcha\CaptchaProviders;
use Baobab\Forms\Captcha\HcaptchaProvider;
use Baobab\Forms\Captcha\TurnstileProvider;

it('lists the two Core providers by default', function () {
    expect(CaptchaProviders::keys())->toBe(['turnstile', 'hcaptcha']);
});

it('resolves each Core provider to its verification class', function () {
    expect(CaptchaProviders::resolve('turnstile'))->toBeInstanceOf(TurnstileProvider::class)
        ->and(CaptchaProviders::resolve('hcaptcha'))->toBeInstanceOf(HcaptchaProvider::class);
});

it('returns null for an unknown provider rather than throwing', function () {
    expect(CaptchaProviders::resolve('recaptcha'))->toBeNull()
        ->and(CaptchaProviders::definition('recaptcha'))->toBeNull();
});

it('lets a module register an additional provider via baobab.forms.captcha.providers (spec 14 §11)', function () {
    Hook::modify('baobab.forms.captcha.providers', function (array $providers): array {
        $providers['recaptcha'] = [
            'class' => TurnstileProvider::class,
            'widget_class' => 'g-recaptcha',
            'script_src' => 'https://example.test/api.js',
            'response_field' => 'g-recaptcha-response',
        ];

        return $providers;
    });

    expect(CaptchaProviders::keys())->toContain('recaptcha')
        ->and(CaptchaProviders::definition('recaptcha')['widget_class'])->toBe('g-recaptcha');
});
