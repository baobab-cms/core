<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Auth\Actions\ConfirmTwoFactorCode;
use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Users\Models\User;
use PragmaRX\Google2FA\Google2FA;

function securityScreenUser(string $email = 'security@example.com'): User
{
    $user = User::create(['name' => 'Security', 'email' => $email, 'password' => 'secret-password']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

function otpFor(User $user): string
{
    $secret = $user->two_factor_secret;

    if ($secret === null) {
        throw new RuntimeException('Expected a two-factor secret to be present.');
    }

    return app(Google2FA::class)->getCurrentOtp($secret);
}

// ── Écran ──────────────────────────────────────────────────────────────────────

it('shows the not-enabled state when the user has no 2FA secret', function () {
    $user = securityScreenUser();

    $this->actingAs($user, 'baobab')
        ->get('/admin/account/security')
        ->assertOk()
        ->assertSee(__('baobab::admin.account.security.enable_action'));
});

it('enables 2FA and flashes the recovery codes once', function () {
    $user = securityScreenUser();

    $response = $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/enable')
        ->assertRedirect('/admin/account/security');

    $user = User::findOrFail($user->id);
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    $location = (string) $response->headers->get('Location');
    $this->actingAs($user, 'baobab')
        ->get($location)
        ->assertOk()
        ->assertSee(__('baobab::admin.account.security.recovery_codes_title'));
});

it('confirms 2FA with a valid TOTP code', function () {
    $user = securityScreenUser();
    app(EnableTwoFactor::class)($user);
    $code = otpFor($user);

    $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/confirm', ['code' => $code])
        ->assertRedirect('/admin/account/security');

    expect(User::findOrFail($user->id)->hasTwoFactorEnabled())->toBeTrue();
});

it('rejects confirmation with an invalid TOTP code', function () {
    $user = securityScreenUser();
    app(EnableTwoFactor::class)($user);
    $validCode = otpFor($user);
    $invalidCode = $validCode === '000000' ? '111111' : '000000';

    $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/confirm', ['code' => $invalidCode])
        ->assertSessionHasErrors('code');

    expect(User::findOrFail($user->id)->hasTwoFactorEnabled())->toBeFalse();
});

// ── Codes de récupération ────────────────────────────────────────────────────

it('refuses to regenerate recovery codes before 2FA is confirmed', function () {
    $user = securityScreenUser();
    app(EnableTwoFactor::class)($user);

    $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/recovery-codes')
        ->assertForbidden();
});

it('regenerates recovery codes once 2FA is confirmed', function () {
    $user = securityScreenUser();
    app(EnableTwoFactor::class)($user);
    app(ConfirmTwoFactorCode::class)($user, otpFor($user));
    $originalCodes = User::findOrFail($user->id)->two_factor_recovery_codes;

    $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/recovery-codes')
        ->assertRedirect('/admin/account/security');

    expect(User::findOrFail($user->id)->two_factor_recovery_codes)->not->toBe($originalCodes);
});

// ── Désactivation ──────────────────────────────────────────────────────────────

it('refuses to disable 2FA with the wrong password', function () {
    $user = securityScreenUser();
    app(EnableTwoFactor::class)($user);
    app(ConfirmTwoFactorCode::class)($user, otpFor($user));

    $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/disable', ['current_password' => 'wrong-password'])
        ->assertSessionHasErrors('current_password');

    expect(User::findOrFail($user->id)->hasTwoFactorEnabled())->toBeTrue();
});

it('disables 2FA with the correct password', function () {
    $user = securityScreenUser();
    app(EnableTwoFactor::class)($user);
    app(ConfirmTwoFactorCode::class)($user, otpFor($user));

    $this->actingAs($user, 'baobab')
        ->post('/admin/account/security/disable', ['current_password' => 'secret-password'])
        ->assertRedirect('/admin/account/security');

    $user = User::findOrFail($user->id);
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull();
});
