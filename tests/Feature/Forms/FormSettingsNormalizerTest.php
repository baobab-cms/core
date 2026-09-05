<?php

use Baobab\Forms\Support\FormSettingsNormalizer;

it('normalizes suites, defaulting every step to disabled', function () {
    $normalized = FormSettingsNormalizer::normalize([]);

    expect($normalized['suites'])->toBe([
        'email_notification' => ['enabled' => false, 'recipients' => []],
        'acknowledgement' => ['enabled' => false],
        'admin_notification' => ['enabled' => false],
        'webhook' => ['enabled' => false],
    ]);
});

it('turns the recipients textarea into a clean list, dropping blank lines', function () {
    $normalized = FormSettingsNormalizer::normalize([
        'suites' => ['email_notification' => ['enabled' => true, 'recipients' => "jane@example.com\n\n john@example.com \n"]],
    ]);

    expect($normalized['suites']['email_notification'])->toBe([
        'enabled' => true,
        'recipients' => ['jane@example.com', 'john@example.com'],
    ]);
});

it('drops the captcha keys when the provider is none, even if submitted', function () {
    $normalized = FormSettingsNormalizer::normalize([
        'captcha_provider' => 'none',
        'captcha_site_key' => 'leaked-anyway',
        'captcha_secret_key' => 'leaked-anyway',
    ]);

    expect($normalized['anti_spam']['captcha'])->toBe([
        'provider' => 'none',
        'site_key' => null,
        'secret_key' => null,
    ]);
});

it('keeps the captcha keys for a real provider', function () {
    $normalized = FormSettingsNormalizer::normalize([
        'captcha_provider' => 'turnstile',
        'captcha_site_key' => '0x123',
        'captcha_secret_key' => '0xabc',
    ]);

    expect($normalized['anti_spam']['captcha'])->toBe([
        'provider' => 'turnstile',
        'site_key' => '0x123',
        'secret_key' => '0xabc',
    ]);
});

it('falls back to none for an unknown provider rather than trusting arbitrary input', function () {
    $normalized = FormSettingsNormalizer::normalize(['captcha_provider' => 'recaptcha']);

    expect($normalized['anti_spam']['captcha']['provider'])->toBe('none');
});

it('normalizes an empty confirmation message to null (Pass C1)', function () {
    expect(FormSettingsNormalizer::normalize([])['confirmation'])->toBe(['message' => null])
        ->and(FormSettingsNormalizer::normalize(['confirmation_message' => '   '])['confirmation'])->toBe(['message' => null]);
});

it('trims the configured confirmation message', function () {
    $normalized = FormSettingsNormalizer::normalize(['confirmation_message' => '  Merci !  ']);

    expect($normalized['confirmation'])->toBe(['message' => 'Merci !']);
});
