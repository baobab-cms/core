<?php

use Baobab\Auth\Actions\ConfirmTwoFactorCode;
use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Users\Models\User;
use PragmaRX\Google2FA\Google2FA;

it('logs a user in and redirects to the dashboard when 2FA is disabled', function () {
    User::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret-password',
    ]);

    $response = $this->post('/login', [
        'email' => 'alice@example.com',
        'password' => 'secret-password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated('baobab');
});

it('redirects to the two-factor challenge without logging in when 2FA is enabled', function () {
    $user = User::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'secret-password',
    ]);
    app(EnableTwoFactor::class)($user);
    $secret = $user->two_factor_secret;
    expect($secret)->not->toBeNull();
    app(ConfirmTwoFactorCode::class)($user, app(Google2FA::class)->getCurrentOtp($secret));

    $response = $this->post('/login', [
        'email' => 'bob@example.com',
        'password' => 'secret-password',
    ]);

    $response->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest('baobab');
    $this->assertTrue(session()->has('baobab.2fa.challenge_user_id'));
});

it('rejects an invalid password', function () {
    User::create([
        'name' => 'Carol',
        'email' => 'carol@example.com',
        'password' => 'secret-password',
    ]);

    $response = $this->post('/login', [
        'email' => 'carol@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('baobab');
});
