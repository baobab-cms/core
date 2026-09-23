<?php

use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\ConfirmTwoFactorCode;
use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use PragmaRX\Google2FA\Google2FA;

/**
 * Mot de passe oublié (spec 04 §9, décision 7) : lien par `core.password_reset`,
 * même réponse que l'adresse existe ou non, toutes les sessions fermées, retour
 * à l'écran de connexion sans connexion automatique.
 */
function resetPasswordUser(string $email): User
{
    return User::create(['name' => 'Reset Person', 'email' => $email, 'password' => 'old-password-long']);
}

function resetPasswordToken(User $user): string
{
    return Password::broker('baobab_users')->createToken($user);
}

it('links the forgot-password screen from the login screen', function () {
    $this->get('/login')->assertOk()->assertSee(route('password.request'), false);

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee(__('baobab::admin.auth.forgot_password_intro'));
});

it('mails a reset link through core.password_reset to a known address', function () {
    Queue::fake();
    $user = resetPasswordUser('reset-known@example.com');

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', __('baobab::admin.auth.reset_link_sent'));

    $link = null;
    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($user, &$link): bool {
        if ($mail->templateKey !== 'core.password_reset' || $mail->to !== $user->email) {
            return false;
        }

        preg_match('/href="([^"]+)"/', $mail->html, $matches);
        $link = html_entity_decode($matches[1] ?? '');

        return true;
    });

    expect($link)->toContain('/reset-password/')
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();

    $this->get((string) $link)->assertOk()->assertSee('name="token"', false);
});

it('answers an unknown address exactly like a known one, and mails nothing', function () {
    Queue::fake();

    $this->post(route('password.email'), ['email' => 'nobody-here@example.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', __('baobab::admin.auth.reset_link_sent'));

    Queue::assertNotPushed(SendQueuedMail::class);
});

it('resets the password, closes every session, rotates the remember token and sends back to the login screen', function () {
    Notification::fake();
    $user = resetPasswordUser('reset-done@example.com');
    $user->forceFill(['remember_token' => 'token-before-reset'])->save();
    DB::table('sessions')->insert([
        ['id' => 'reset-sess-a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'reset-sess-b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    $this->post(route('password.update'), [
        'token' => resetPasswordToken($user),
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('baobab::admin.auth.password_reset_done'));

    $this->assertGuest('baobab');

    $fresh = $user->fresh();
    expect(Hash::check('brand-new-password', $fresh->password))->toBeTrue()
        ->and($fresh->remember_token)->not->toBe('token-before-reset')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse()
        ->and(AuditEntry::where('action', 'user.password.reset')->where('auditable_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo(
        $user,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($user)['key'] === 'core.security.password_changed',
    );
});

it('refuses an unknown or expired token and leaves the password untouched', function (string $case) {
    $user = resetPasswordUser("reset-{$case}@example.com");
    $token = resetPasswordToken($user);

    if ($case === 'expired') {
        $this->travel(61)->minutes();
    }

    $this->post(route('password.update'), [
        'token' => $case === 'unknown' ? 'not-the-token' : $token,
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors(['email' => __('baobab::admin.auth.reset_link_invalid')]);

    expect(Hash::check('old-password-long', $user->fresh()->password))->toBeTrue();
})->with(['unknown', 'expired']);

it('enforces the password policy on reset', function () {
    $user = resetPasswordUser('reset-weak@example.com');

    $this->post(route('password.update'), [
        'token' => resetPasswordToken($user),
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password-long', $user->fresh()->password))->toBeTrue();
});

it('still asks for the 2FA code after a reset — the link alone never opens a session', function () {
    $user = resetPasswordUser('reset-2fa@example.com');
    app(EnableTwoFactor::class)($user);
    app(ConfirmTwoFactorCode::class)($user, app(Google2FA::class)->getCurrentOtp((string) $user->two_factor_secret));

    $this->post(route('password.update'), [
        'token' => resetPasswordToken($user),
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'));

    $this->assertGuest('baobab');

    $this->post('/login', ['email' => $user->email, 'password' => 'brand-new-password'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest('baobab');
});
