<?php

use Baobab\Auth\Actions\ConfirmTwoFactorCode;
use Baobab\Auth\Actions\DisableTwoFactor;
use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Users\Models\User;
use PragmaRX\Google2FA\Google2FA;

// ── Cycle de vie du 2FA ────────────────────────────────────────────────────────

it('enables 2FA, confirms it with a valid TOTP code, then disables it', function () {
    $user = User::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    app(EnableTwoFactor::class)($user);

    $secret = $user->two_factor_secret;

    expect($secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    if ($secret === null) {
        throw new RuntimeException('Expected a two-factor secret to be present.');
    }

    app(ConfirmTwoFactorCode::class)($user, app(Google2FA::class)->getCurrentOtp($secret));

    $user = User::findOrFail($user->id);
    expect($user->hasTwoFactorEnabled())->toBeTrue();

    app(DisableTwoFactor::class)($user);

    $user = User::findOrFail($user->id);
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull();
});

it('ConfirmTwoFactorCode rejects an invalid TOTP code', function () {
    $user = User::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret',
    ]);

    app(EnableTwoFactor::class)($user);

    $secret = $user->two_factor_secret;

    if ($secret === null) {
        throw new RuntimeException('Expected a two-factor secret to be present.');
    }

    $validCode = app(Google2FA::class)->getCurrentOtp($secret);
    $invalidCode = $validCode === '000000' ? '111111' : '000000';

    expect(fn () => app(ConfirmTwoFactorCode::class)($user, $invalidCode))
        ->toThrow(InvalidArgumentException::class);
});
