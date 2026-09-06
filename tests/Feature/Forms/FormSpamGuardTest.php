<?php

use Baobab\Facades\Hook;
use Baobab\Forms\Support\FormSpamGuard;

it('is not triggered by a clean submission with no anti-spam fields at all', function () {
    expect(FormSpamGuard::isTriggered([]))->toBeFalse();
});

it('is triggered when the honeypot field is filled', function () {
    expect(FormSpamGuard::isTriggered([FormSpamGuard::HONEYPOT_FIELD => 'i am a bot']))->toBeTrue()
        ->and(FormSpamGuard::isTriggered([FormSpamGuard::HONEYPOT_FIELD => '   ']))->toBeTrue();
});

it('is not triggered when the honeypot field is present but empty', function () {
    expect(FormSpamGuard::isTriggered([FormSpamGuard::HONEYPOT_FIELD => '']))->toBeFalse();
});

it('is triggered when the render token is present but unreadable (tampered)', function () {
    expect(FormSpamGuard::isTriggered([FormSpamGuard::TIMESTAMP_FIELD => 'not-a-real-token']))->toBeTrue();
});

it('is triggered when submitted faster than the minimum elapsed threshold', function () {
    $token = FormSpamGuard::renderToken();

    expect(FormSpamGuard::isTriggered([FormSpamGuard::TIMESTAMP_FIELD => $token]))->toBeTrue();
});

it('is not triggered once enough time has elapsed since the token was rendered', function () {
    $token = FormSpamGuard::renderToken();

    $this->travel(3)->seconds();

    expect(FormSpamGuard::isTriggered([FormSpamGuard::TIMESTAMP_FIELD => $token]))->toBeFalse();
});

it('lets baobab.form.anti_spam.min_elapsed_seconds raise or lower the threshold', function () {
    $token = FormSpamGuard::renderToken();

    Hook::modify('baobab.form.anti_spam.min_elapsed_seconds', fn (): int => 10);

    $this->travel(3)->seconds();

    expect(FormSpamGuard::isTriggered([FormSpamGuard::TIMESTAMP_FIELD => $token]))->toBeTrue();
});
