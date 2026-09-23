<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Auth\Actions\RevokeSession;
use Baobab\Auth\RememberDuration;
use Baobab\Privacy\Actions\BuildCookieRegister;
use Baobab\Privacy\Cookies\CookieDeclaration;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

/**
 * « Se souvenir de moi » (spec 04 §9, décision 6) : cookie posé selon la
 * case, après le défi 2FA seulement, jeton qui tourne à la révocation d'une
 * session, durée configurable.
 */
function rememberMeUser(string $email): User
{
    $user = User::create(['name' => 'Remember', 'email' => $email, 'password' => 'secret-password']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

function rememberCookieName(): string
{
    return Auth::guard('baobab')->getRecallerName();
}

it('sets a 30-day remember cookie when the box is checked', function () {
    $user = rememberMeUser('remember-checked@example.com');

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret-password', 'remember' => '1'])
        ->assertRedirect(route('admin.dashboard'));

    $response->assertCookie(rememberCookieName());

    $expires = $response->getCookie(rememberCookieName(), false)?->getExpiresTime();

    expect($expires)->toBeGreaterThan(now()->addDays(29)->timestamp)
        ->and($expires)->toBeLessThanOrEqual(now()->addDays(30)->addMinute()->timestamp)
        ->and($user->fresh()->remember_token)->not->toBeNull();
});

it('sets no remember cookie when the box is left unchecked', function () {
    $user = rememberMeUser('remember-unchecked@example.com');

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect(route('admin.dashboard'))
        ->assertCookieMissing(rememberCookieName());
});

it('shows the remember box on the login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('name="remember"', false)
        ->assertSee(__('baobab::admin.auth.remember'));
});

it('carries the choice through the 2FA challenge and only sets the cookie once the code is valid', function () {
    $user = rememberMeUser('remember-2fa@example.com');
    app(EnableTwoFactor::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password', 'remember' => '1'])
        ->assertRedirect(route('two-factor.challenge'))
        ->assertCookieMissing(rememberCookieName());

    expect(session('baobab.2fa.remember'))->toBeTrue();

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->fresh()->two_factor_secret);

    $this->post('/two-factor-challenge', ['code' => $code])
        ->assertRedirect(route('admin.dashboard'))
        ->assertCookie(rememberCookieName());

    expect(session()->has('baobab.2fa.remember'))->toBeFalse();
});

it('does not remember a 2FA login when the box was left unchecked', function () {
    $user = rememberMeUser('remember-2fa-unchecked@example.com');
    app(EnableTwoFactor::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect(route('two-factor.challenge'));

    $code = app(Google2FA::class)->getCurrentOtp((string) $user->fresh()->two_factor_secret);

    $this->post('/two-factor-challenge', ['code' => $code])
        ->assertRedirect(route('admin.dashboard'))
        ->assertCookieMissing(rememberCookieName());
});

it('stops an existing remember cookie from logging back in once a session is revoked', function () {
    $user = rememberMeUser('remember-revoked@example.com');
    $user->forceFill(['remember_token' => 'token-before-revocation'])->save();

    $recaller = $user->id.'|token-before-revocation|'.$user->getAuthPassword();

    $this->withCookie(rememberCookieName(), $recaller)
        ->get(route('admin.dashboard'))
        ->assertOk();

    Auth::guard('baobab')->logout();
    $user->forceFill(['remember_token' => 'token-before-revocation'])->save();

    app(RevokeSession::class)($user, 'any-session-id');

    expect($user->fresh()->remember_token)->not->toBe('token-before-revocation')
        ->and($user->fresh()->remember_token)->not->toBeNull();

    $this->withCookie(rememberCookieName(), $recaller)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

function declaredRememberDuration(): string
{
    $declared = array_values(array_filter(
        app(BuildCookieRegister::class)()->cookies,
        fn (CookieDeclaration $cookie): bool => $cookie->name === 'remember_baobab_*',
    ));

    expect($declared)->toHaveCount(1);

    return $declared[0]->duration;
}

it('derives the guard duration and the declared cookie from baobab.auth.remember_days', function () {
    expect(config('auth.guards.baobab.remember'))->toBe(30 * 1440);

    config(['baobab.auth.remember_days' => 7]);

    expect(RememberDuration::minutes())->toBe(7 * 1440)
        ->and(declaredRememberDuration())->toBe(trans_choice('baobab::privacy.cookies.durations.days', 7, ['count' => 7]));
});

it('falls back to 30 days when a published config/baobab.php lacks remember_days (suivi n° 119)', function (mixed $value) {
    // Reproduit le banc d'essai : un bloc `auth` publié avant l'ajout de la
    // clé écrase celui du package, `remember_days` n'existe pas.
    config(['baobab.auth' => $value === 'absent' ? ['user_model' => User::class] : ['user_model' => User::class, 'remember_days' => $value]]);

    expect(RememberDuration::days())->toBe(30)
        ->and(declaredRememberDuration())->toBe(trans_choice('baobab::privacy.cookies.durations.days', 30, ['count' => 30]));
})->with(['absent', null, 0, -5, '']);
