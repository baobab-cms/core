<?php

use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

it('logs the user in with a valid TOTP code', function () {
    $user = User::create([
        'name' => 'Dave',
        'email' => 'dave@example.com',
        'password' => 'secret-password',
    ]);
    app(EnableTwoFactor::class)($user);

    $this->withSession(['baobab.2fa.challenge_user_id' => $user->id]);

    $secret = $user->two_factor_secret;
    expect($secret)->not->toBeNull();

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $response = $this->post('/two-factor-challenge', ['code' => $code]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user, 'baobab');
    $this->assertFalse(session()->has('baobab.2fa.challenge_user_id'));
});

it('rejects an invalid TOTP code and keeps the user unauthenticated', function () {
    $user = User::create([
        'name' => 'Erin',
        'email' => 'erin@example.com',
        'password' => 'secret-password',
    ]);
    app(EnableTwoFactor::class)($user);

    $this->withSession(['baobab.2fa.challenge_user_id' => $user->id]);

    $secret = $user->two_factor_secret;
    expect($secret)->not->toBeNull();

    $validCode = app(Google2FA::class)->getCurrentOtp($secret);
    $invalidCode = $validCode === '000000' ? '111111' : '000000';

    $response = $this->post('/two-factor-challenge', ['code' => $invalidCode]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest('baobab');
});

it('redirects to login when hitting the challenge without a pending session', function () {
    $this->get('/two-factor-challenge')->assertRedirect(route('login'));
});

it('logs the user in with a valid recovery code and consumes it', function () {
    $user = User::create([
        'name' => 'Fay',
        'email' => 'fay@example.com',
        'password' => 'secret-password',
    ]);
    app(EnableTwoFactor::class)($user);

    $recoveryCodes = $user->two_factor_recovery_codes;
    expect($recoveryCodes)->toBeArray()->not->toBeEmpty();
    $recoveryCode = $recoveryCodes[0];

    $this->withSession(['baobab.2fa.challenge_user_id' => $user->id]);

    $response = $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user, 'baobab');

    $remainingCodes = $user->fresh()->two_factor_recovery_codes;
    expect($remainingCodes)->not->toContain($recoveryCode)
        ->and($remainingCodes)->toHaveCount(count($recoveryCodes) - 1);
});

it('rejects an already-used recovery code', function () {
    $user = User::create([
        'name' => 'Gus',
        'email' => 'gus@example.com',
        'password' => 'secret-password',
    ]);
    app(EnableTwoFactor::class)($user);

    $recoveryCode = $user->two_factor_recovery_codes[0];

    $this->withSession(['baobab.2fa.challenge_user_id' => $user->id]);
    $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])
        ->assertRedirect(route('admin.dashboard'));

    Auth::guard('baobab')->logout();
    $this->withSession(['baobab.2fa.challenge_user_id' => $user->id]);

    $response = $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode]);

    $response->assertSessionHasErrors('recovery_code');
    $this->assertGuest('baobab');
});

it('rejects an unknown recovery code', function () {
    $user = User::create([
        'name' => 'Hana',
        'email' => 'hana@example.com',
        'password' => 'secret-password',
    ]);
    app(EnableTwoFactor::class)($user);

    $this->withSession(['baobab.2fa.challenge_user_id' => $user->id]);

    $response = $this->post('/two-factor-challenge', ['recovery_code' => 'NOT-A-REAL-CODE']);

    $response->assertSessionHasErrors('recovery_code');
    $this->assertGuest('baobab');
});
