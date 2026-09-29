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

/**
 * La carte de connexion reste centrée — garde de non-régression du n° 227.
 *
 * Le défaut n'était pas dans une feuille de style mais dans la **mise en
 * page** : le corps du layout invité était un flex **en rangée**, et le jour
 * où `<x-baobab::toasts />` a quitté le flottement (24 août 2026, n° 205) il
 * est devenu un second enfant réclamant la largeur. `justify-center` n'avait
 * plus d'espace à répartir, et la carte se collait à gauche.
 *
 * Le test porte donc sur la **direction** : deux enfants sur une rangée, c'est
 * le défaut lui-même ; en colonne, chacun est centré. C'est un test de
 * présentation, assumé comme tel — ce défaut a traversé deux hébergements et
 * une semaine sans que rien ne le voie, faute de quoi que ce soit qui regarde.
 *
 * `login` a son propre layout depuis la Pass D du M9 point 5 (suivi n° 373
 * décision 5, `layouts/login.blade.php`) : la direction/le centrage horizontal
 * restent inconditionnels (`flex flex-col items-center`), mais le centrage
 * vertical (`min-h-svh`/`justify-center`) est réservé à `sm:` et plus — sur
 * mobile, centrer un formulaire court dans toute la hauteur de l'écran ne
 * fait que le repousser sous le pli (retour utilisateur, deux allers-retours
 * avant ce réglage).
 */
it('garde la carte de connexion centrée, toasts compris', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('flex flex-col items-center', false)
        ->assertSee('sm:min-h-svh sm:justify-center', false);
});
