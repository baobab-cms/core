<?php

use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Users\Models\User;
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
