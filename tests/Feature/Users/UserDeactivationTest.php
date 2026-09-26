<?php

use Baobab\Access\AccessManager;
use Baobab\Access\Actions\RemoveRole;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Auth\Actions\ResetPassword;
use Baobab\Auth\Actions\SendPasswordResetLink;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Privacy\Providers\UsersProvider;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\ErasureGuard;
use Baobab\Users\Actions\DeactivateUser;
use Baobab\Users\Actions\Impersonate;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Actions\ReactivateUser;
use Baobab\Users\Exceptions\ImpersonationException;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Désactivation d'un compte actif (spec 05 §5, décision 5 k) : blocage total
 * et immédiat, trois garde-fous, réactivation avec nouveau mot de passe et 2FA
 * réinitialisée.
 */
function deactivationUser(string $role, string $email): User
{
    $user = User::create(['name' => ucfirst($role).' '.$email, 'email' => $email, 'password' => 'secret-password']);
    $user->assignRole(Role::findByName($role, 'baobab'));

    return $user;
}

/** Un acteur de niveau supérieur à celui du Super Admin : seul à pouvoir en désactiver un. */
function deactivationOwner(string $email = 'deactivation-owner@example.com'): User
{
    $role = app(AccessManager::class)->createRole('deactivation-owner', 120);
    $owner = User::create(['name' => 'Owner', 'email' => $email, 'password' => 'secret-password']);
    $owner->assignRole($role);

    return $owner;
}

/** Retire un template du registre : l'envoi échoue, comme un transport ou un template absent. */
function deactivationWithoutTemplate(string $key): void
{
    config(['baobab.mail.templates' => collect(config('baobab.mail.templates'))
        ->reject(fn (array $template): bool => $template['key'] === $key)
        ->values()
        ->all()]);
}

/** Le lien de réinitialisation envoyé à `$to`, tel que l'utilisateur le recevrait. */
function deactivationResetLink(string $to): string
{
    $link = null;

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($to, &$link): bool {
        if ($mail->templateKey !== 'core.password_reset' || $mail->to !== $to) {
            return false;
        }

        preg_match('/href="([^"]+)"/', $mail->html, $matches);
        $link = html_entity_decode($matches[1] ?? '');

        return true;
    });

    return (string) $link;
}

it('blocks the account at once: flag, sessions, api tokens, remember token and pending reset link', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin@example.com');
    $author = deactivationUser('author', 'deact-author@example.com');
    $author->forceFill(['remember_token' => 'old-remember-token'])->save();
    app(CreateApiToken::class)($author, 'deact-token', []);
    DB::table('sessions')->insert(['id' => 'deact-session', 'user_id' => $author->id, 'payload' => '', 'last_activity' => time()]);
    Password::broker('baobab_users')->createToken($author);

    app(DeactivateUser::class)($admin, $author, '  Left the company  ');

    $author = $author->fresh();

    expect($author->isDeactivated())->toBeTrue()
        ->and($author->deactivation_reason)->toBe('Left the company')
        ->and($author->remember_token)->not->toBe('old-remember-token')
        ->and($author->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $author->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $author->email)->count())->toBe(0);
});

it('keeps the roles and the account itself in place', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-keep@example.com');
    $author = deactivationUser('author', 'deact-author-keep@example.com');

    app(DeactivateUser::class)($admin, $author);

    expect(User::query()->whereKey($author->id)->exists())->toBeTrue()
        ->and($author->fresh()->hasRole('author', 'baobab'))->toBeTrue();
});

it('treats a blank reason as no reason and refuses one that is too long', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-reason@example.com');
    $author = deactivationUser('author', 'deact-author-reason@example.com');

    expect(fn () => app(DeactivateUser::class)($admin, $author, str_repeat('x', 501)))
        ->toThrow(ValidationException::class)
        ->and($author->fresh()->isDeactivated())->toBeFalse();

    app(DeactivateUser::class)($admin, $author, '   ');

    expect($author->fresh()->isDeactivated())->toBeTrue()
        ->and($author->fresh()->deactivation_reason)->toBeNull();
});

it('audits the deactivation with its reason and never sends the reason to the user', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-audit@example.com');
    $author = deactivationUser('author', 'deact-author-audit@example.com');

    app(DeactivateUser::class)($admin, $author, 'Confidential reason');

    $entry = AuditEntry::query()->where('action', 'user.deactivated')->where('auditable_id', $author->id)->firstOrFail();

    expect($entry->data['reason'])->toBe('Confidential reason');

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($author): bool {
        return $mail->templateKey === 'core.account_deactivated'
            && $mail->to === $author->email
            && ! str_contains($mail->html, 'Confidential reason');
    });
});

it('audits a deactivation without a reason too', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-noreason@example.com');
    $author = deactivationUser('author', 'deact-author-noreason@example.com');

    app(DeactivateUser::class)($admin, $author);

    $entry = AuditEntry::query()->where('action', 'user.deactivated')->where('auditable_id', $author->id)->firstOrFail();

    expect($entry->data)->not->toHaveKey('reason');
});

it('stays deactivated when the information e-mail cannot be queued', function () {
    deactivationWithoutTemplate('core.account_deactivated');
    $admin = deactivationUser('admin', 'deact-admin-mailfail@example.com');
    $author = deactivationUser('author', 'deact-author-mailfail@example.com');

    app(DeactivateUser::class)($admin, $author);

    expect($author->fresh()->isDeactivated())->toBeTrue();
});

it('refuses to deactivate yourself, a peer or a superior', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-guard@example.com');
    $peer = deactivationUser('admin', 'deact-peer@example.com');
    $superAdmin = deactivationUser('super-admin', 'deact-super@example.com');

    foreach ([$admin, $peer, $superAdmin] as $target) {
        expect(fn () => app(DeactivateUser::class)($admin, $target))->toThrow(HierarchyViolationException::class)
            ->and($target->fresh()->isDeactivated())->toBeFalse();
    }
});

it('never deactivates the last active super-admin, and a deactivated one does not count', function () {
    Queue::fake();
    $owner = deactivationOwner();
    $first = deactivationUser('super-admin', 'deact-super-first@example.com');
    $second = deactivationUser('super-admin', 'deact-super-second@example.com');

    app(DeactivateUser::class)($owner, $first);

    expect(fn () => app(DeactivateUser::class)($owner, $second))->toThrow(AdminLockoutException::class)
        ->and($second->fresh()->isDeactivated())->toBeFalse();
});

it('refuses a deactivation that has no object: already deactivated or invitation pending', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-state@example.com');
    $author = deactivationUser('author', 'deact-author-state@example.com');
    $invited = app(InviteUser::class)($admin, 'Invited', 'deact-invited@example.com', 'author');

    app(DeactivateUser::class)($admin, $author);

    expect(fn () => app(DeactivateUser::class)($admin, $author))->toThrow(InvalidAccountStateException::class)
        ->and(fn () => app(DeactivateUser::class)($admin, $invited))->toThrow(InvalidAccountStateException::class)
        ->and($invited->fresh()->isDeactivated())->toBeFalse();
});

it('refuses the login of a deactivated account with the same message as a wrong password', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-login@example.com');
    $author = deactivationUser('author', 'deact-author-login@example.com');

    app(DeactivateUser::class)($admin, $author);

    $this->post(route('login'), ['email' => $author->email, 'password' => 'secret-password'])
        ->assertSessionHasErrors(['email' => __('baobab::admin.auth.failed')]);

    $this->assertGuest('baobab');
});

it('sends no reset link to a deactivated account', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-forgot@example.com');
    $author = deactivationUser('author', 'deact-author-forgot@example.com');

    app(DeactivateUser::class)($admin, $author);
    Queue::fake();

    app(SendPasswordResetLink::class)($author->email);

    Queue::assertNothingPushed();
});

it('refuses a password reset on a deactivated account, even with a token that predates it', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-reset@example.com');
    $author = deactivationUser('author', 'deact-author-reset@example.com');
    $token = Password::broker('baobab_users')->createToken($author);

    app(DeactivateUser::class)($admin, $author);

    // Le jeton est supprimé à la désactivation ; on le rétablit pour prouver
    // que la condition d'authentification suffit à elle seule.
    DB::table('password_reset_tokens')->insert(['email' => $author->email, 'token' => Hash::make($token), 'created_at' => now()]);

    expect(fn () => app(ResetPassword::class)($author->email, $token, 'Another-Str0ng-Passw0rd!'))
        ->toThrow(ValidationException::class);

    expect(Hash::check('Another-Str0ng-Passw0rd!', (string) $author->fresh()->password))->toBeFalse();
});

it('drops a two-factor challenge that was started before the deactivation', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'deact-admin-2fa@example.com');
    $author = deactivationUser('author', 'deact-author-2fa@example.com');

    app(DeactivateUser::class)($admin, $author);

    $this->withSession(['baobab.2fa.challenge_user_id' => $author->id, 'baobab.2fa.remember' => false])
        ->post(route('two-factor.challenge'), ['code' => '123456'])
        ->assertRedirect(route('login'))
        ->assertSessionMissing('baobab.2fa.challenge_user_id');

    $this->assertGuest('baobab');
});

it('refuses to impersonate a deactivated account', function () {
    Queue::fake();
    $superAdmin = deactivationUser('super-admin', 'deact-super-impersonate@example.com');
    $author = deactivationUser('author', 'deact-author-impersonate@example.com');
    $this->actingAs($superAdmin, 'baobab');

    app(DeactivateUser::class)(deactivationOwner('deact-owner-impersonate@example.com'), $author);

    expect(fn () => app(Impersonate::class)($superAdmin, $author))->toThrow(ImpersonationException::class);
});

it('ends an impersonation in progress when its target is deactivated', function () {
    Queue::fake();
    $author = deactivationUser('author', 'deact-author-inflight@example.com');
    $admin = deactivationUser('admin', 'deact-admin-inflight@example.com');

    // L'usurpation connecte l'acteur comme la cible, dans sa propre session :
    // la ligne de session porte l'identifiant de la cible.
    DB::table('sessions')->insert(['id' => 'impersonating', 'user_id' => $author->id, 'payload' => '', 'last_activity' => time()]);

    app(DeactivateUser::class)($admin, $author);

    expect(DB::table('sessions')->where('id', 'impersonating')->exists())->toBeFalse();
});

it('reactivates with a new password to choose, a reset two-factor and kept roles', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'react-admin@example.com');
    $author = deactivationUser('author', 'react-author@example.com');
    $author->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_recovery_codes' => ['a', 'b'], 'two_factor_confirmed_at' => now()])->save();
    app(DeactivateUser::class)($admin, $author, 'Compromised');
    Queue::fake();

    app(ReactivateUser::class)($admin, $author->fresh(), 'False alarm');

    $author = $author->fresh();

    expect($author->isDeactivated())->toBeFalse()
        ->and($author->deactivation_reason)->toBeNull()
        ->and(Hash::check('secret-password', (string) $author->password))->toBeFalse()
        ->and($author->hasTwoFactorEnabled())->toBeFalse()
        ->and($author->two_factor_secret)->toBeNull()
        ->and($author->hasRole('author', 'baobab'))->toBeTrue()
        ->and($author->tokens()->count())->toBe(0)
        ->and(AuditEntry::query()->where('action', 'user.reactivated')->where('auditable_id', $author->id)->firstOrFail()->data['reason'])->toBe('False alarm');

    expect(deactivationResetLink($author->email))->toContain('/reset-password/');
});

it('lets the reactivated user choose a new password and sign in with it', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'react-admin-flow@example.com');
    $author = deactivationUser('author', 'react-author-flow@example.com');
    app(DeactivateUser::class)($admin, $author);
    Queue::fake();
    app(ReactivateUser::class)($admin, $author->fresh());

    $link = deactivationResetLink($author->email);
    preg_match('#/reset-password/([^?]+)\?email=(.+)$#', $link, $matches);

    app(ResetPassword::class)(urldecode($matches[2]), $matches[1], 'Brand-New-Str0ng-Passw0rd!');

    $this->post(route('login'), ['email' => $author->email, 'password' => 'Brand-New-Str0ng-Passw0rd!'])
        ->assertRedirect(route('admin.dashboard'));
});

it('refuses to reactivate an account that is not deactivated, or above the actor', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'react-admin-guard@example.com');
    $author = deactivationUser('author', 'react-author-guard@example.com');
    $owner = deactivationOwner('react-owner-guard@example.com');
    $superAdmin = deactivationUser('super-admin', 'react-super-guard@example.com');
    $spare = deactivationUser('super-admin', 'react-super-spare@example.com');

    expect(fn () => app(ReactivateUser::class)($admin, $author))->toThrow(InvalidAccountStateException::class);

    app(DeactivateUser::class)($owner, $superAdmin);

    expect(fn () => app(ReactivateUser::class)($admin, $superAdmin->fresh()))->toThrow(HierarchyViolationException::class)
        ->and($superAdmin->fresh()->isDeactivated())->toBeTrue()
        ->and($spare->fresh()->isDeactivated())->toBeFalse();
});

it('reactivates nothing when the password link cannot be sent', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'react-admin-mailfail@example.com');
    $author = deactivationUser('author', 'react-author-mailfail@example.com');
    app(DeactivateUser::class)($admin, $author);
    deactivationWithoutTemplate('core.password_reset');

    expect(fn () => app(ReactivateUser::class)($admin, $author->fresh()))->toThrow(MailTemplateNotFoundException::class)
        ->and($author->fresh()->isDeactivated())->toBeTrue();
});

it('does not restore an api token at reactivation', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'react-admin-token@example.com');
    $author = deactivationUser('author', 'react-author-token@example.com');
    app(CreateApiToken::class)($author, 'react-token', []);

    app(DeactivateUser::class)($admin, $author);
    app(ReactivateUser::class)($admin, $author->fresh());

    expect($author->fresh()->tokens()->count())->toBe(0);
});

it('counts only active super-admins when guarding the last one', function () {
    Queue::fake();
    $owner = deactivationOwner('guard-owner@example.com');
    $first = deactivationUser('super-admin', 'guard-super-first@example.com');
    $second = deactivationUser('super-admin', 'guard-super-second@example.com');
    $superRole = Role::findByName('super-admin', 'baobab');

    // Deux Super Admins actifs : aucun n'est « le dernier ».
    expect(app(AccessManager::class)->isLastActiveSuperAdmin($first))->toBeFalse();

    app(DeactivateUser::class)($owner, $second);

    // Le second est désactivé : le premier reste le seul actif, et retirer
    // son rôle laisserait le site sans accès complet.
    expect(app(AccessManager::class)->isLastActiveSuperAdmin($first))->toBeTrue()
        ->and(fn () => app(RemoveRole::class)($first, $superRole))->toThrow(AdminLockoutException::class)
        ->and(app(AccessManager::class)->isLastActiveSuperAdmin($second->fresh()))->toBeFalse();

    // Retirer le rôle d'un Super Admin déjà désactivé ne coûte aucun accès.
    app(RemoveRole::class)($second->fresh(), $superRole);

    expect($second->fresh()->hasRole('super-admin', 'baobab'))->toBeFalse();
});

it('refuses to erase the last active super-admin even when another one is deactivated', function () {
    Queue::fake();
    $owner = deactivationOwner('erase-owner@example.com');
    $first = deactivationUser('super-admin', 'erase-super-first@example.com');
    $second = deactivationUser('super-admin', 'erase-super-second@example.com');

    app(DeactivateUser::class)($owner, $second);

    expect(fn () => app(ErasureGuard::class)->assertErasable(new Subject($first->id, null)))
        ->toThrow(AdminLockoutException::class);

    // Effacer le désactivé, en revanche, est sans risque.
    app(ErasureGuard::class)->assertErasable(new Subject($second->id, null));

    expect(true)->toBeTrue();
});

it('exports the deactivation reason with the profile and erases it with the account', function () {
    Queue::fake();
    $admin = deactivationUser('admin', 'privacy-admin@example.com');
    $author = deactivationUser('author', 'privacy-author@example.com');
    app(DeactivateUser::class)($admin, $author, 'Personal note about this person');

    $subject = new Subject($author->id, $author->email);
    $export = app(UsersProvider::class)->export($subject);

    expect($export->data['profile']['deactivation_reason'])->toBe('Personal note about this person')
        ->and($export->data['profile']['deactivated_at'])->not->toBeNull();

    app(UsersProvider::class)->erase($subject);

    expect($author->fresh()->deactivation_reason)->toBeNull();
});
