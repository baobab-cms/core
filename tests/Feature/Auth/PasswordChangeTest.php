<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Admin\Account\Http\Requests\ChangePasswordRequest;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\ChangePassword;
use Baobab\Facades\Hook;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Changement du mot de passe depuis Mon compte → Sécurité (spec 04 §9,
 * décision 7) : mot de passe actuel exigé, autres sessions fermées, jeton
 * « se souvenir de moi » renouvelé, interdit pendant une impersonation.
 */
function passwordChangeUser(string $email): User
{
    $user = User::create(['name' => 'Change Person', 'email' => $email, 'password' => 'current-password-long']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

function passwordChangeSession(string $id, User $user): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
}

it('shows the password card on the account security screen', function () {
    $user = passwordChangeUser('change-screen@example.com');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.account.security.show'))
        ->assertOk()
        ->assertSee(__('baobab::admin.account.security.password_title'))
        ->assertSee(route('admin.account.security.password.update'), false);
});

it('changes the password from the account screen', function () {
    Notification::fake();
    $user = passwordChangeUser('change-ok@example.com');

    $this->actingAs($user, 'baobab')
        ->put(route('admin.account.security.password.update'), [
            'current_password' => 'current-password-long',
            'password' => 'another-long-password',
            'password_confirmation' => 'another-long-password',
        ])
        ->assertRedirect(route('admin.account.security.show'))
        ->assertSessionHasNoErrors();

    expect(Hash::check('another-long-password', $user->fresh()->password))->toBeTrue()
        ->and(AuditEntry::where('action', 'user.password.changed')->where('auditable_id', $user->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user, 'baobab');

    Notification::assertSentTo(
        $user,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($user)['key'] === 'core.security.password_changed',
    );
});

it('closes the other sessions and rotates the remember token, keeping the current session', function () {
    $user = passwordChangeUser('change-sessions@example.com');
    $user->forceFill(['remember_token' => 'token-before-change'])->save();
    passwordChangeSession('change-current', $user);
    passwordChangeSession('change-other', $user);

    app(ChangePassword::class)($user, 'current-password-long', 'another-long-password', 'change-current');

    expect(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe(['change-current'])
        ->and($user->fresh()->remember_token)->not->toBe('token-before-change');
});

it('stops an existing remember cookie from logging back in once the password changed', function () {
    $user = passwordChangeUser('change-cookie@example.com');
    $user->forceFill(['remember_token' => 'token-before-change'])->save();
    $cookie = Auth::guard('baobab')->getRecallerName();
    $recaller = $user->id.'|token-before-change|'.$user->getAuthPassword();

    app(ChangePassword::class)($user, 'current-password-long', 'another-long-password');

    $this->withCookie($cookie, $recaller)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

it('rejects a wrong current password, in its own error bag', function () {
    $user = passwordChangeUser('change-wrong@example.com');

    $this->actingAs($user, 'baobab')
        ->put(route('admin.account.security.password.update'), [
            'current_password' => 'not-my-password',
            'password' => 'another-long-password',
            'password_confirmation' => 'another-long-password',
        ])
        ->assertSessionHasErrorsIn(ChangePasswordRequest::ERROR_BAG, ['current_password']);

    expect(Hash::check('current-password-long', $user->fresh()->password))->toBeTrue();
});

it('rejects a weak or unconfirmed new password', function (string $password, string $confirmation) {
    $user = passwordChangeUser("change-invalid-{$password}@example.com");

    $this->actingAs($user, 'baobab')
        ->put(route('admin.account.security.password.update'), [
            'current_password' => 'current-password-long',
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])
        ->assertSessionHasErrorsIn(ChangePasswordRequest::ERROR_BAG, ['password']);

    expect(Hash::check('current-password-long', $user->fresh()->password))->toBeTrue();
})->with([
    'too short' => ['eleven-char', 'eleven-char'],
    'not confirmed' => ['another-long-password', 'a-different-password'],
]);

it('lets the host application override the password policy', function () {
    Password::defaults(fn (): Password => Password::min(20));
    $user = passwordChangeUser('change-host-policy@example.com');

    expect(fn () => app(ChangePassword::class)($user, 'current-password-long', 'only-16-characters'))
        ->toThrow(ValidationException::class);
});

it('blocks the password change while impersonating', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->put(route('admin.account.security.password.update'), [
        'current_password' => 'secret',
        'password' => 'another-long-password',
        'password_confirmation' => 'another-long-password',
    ])->assertForbidden();
});

it('emits baobab.user.password.changed', function () {
    $user = passwordChangeUser('change-hook@example.com');
    $received = null;
    Hook::listen('baobab.user.password.changed', function (User $changed) use (&$received): void {
        $received = $changed;
    });

    app(ChangePassword::class)($user, 'current-password-long', 'another-long-password');

    expect($received?->is($user))->toBeTrue();
});
