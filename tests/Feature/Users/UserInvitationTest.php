<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Users\Actions\CancelUserInvitation;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Actions\SendUserInvitation;
use Baobab\Users\Exceptions\InvitationNotPendingException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Invitation d'un utilisateur (spec 05 §5, décision 5) : compte créé sans mot
 * de passe utilisable, lien `core.user_invited` valable 24 heures, renvoi,
 * acceptation qui renvoie à la connexion.
 */
function invitingAdmin(): User
{
    $admin = User::create(['name' => 'Inviting Admin', 'email' => 'inviting-admin-'.uniqid().'@example.com', 'password' => 'secret-password']);
    $admin->assignRole(Role::findByName('admin', 'baobab'));

    return $admin;
}

/** Le lien d'invitation envoyé à `$to`, tel que l'invité le recevrait. */
function invitationLink(string $to): string
{
    $link = null;

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($to, &$link): bool {
        if ($mail->templateKey !== 'core.user_invited' || $mail->to !== $to) {
            return false;
        }

        preg_match('/href="([^"]+)"/', $mail->html, $matches);
        $link = html_entity_decode($matches[1] ?? '');

        return true;
    });

    return (string) $link;
}

/** @return array{0: string, 1: string} jeton et e-mail extraits du lien */
function invitationTokenAndEmail(string $link): array
{
    preg_match('#/invitation/([^?]+)\?email=(.+)$#', $link, $matches);

    return [$matches[1], urldecode($matches[2])];
}

it('grants baobab.users.manage to the admin role', function () {
    expect(Role::findByName('admin', 'baobab')->hasPermissionTo('baobab.users.manage', 'baobab'))->toBeTrue();
});

it('opens the user list to users.manage alone, and shows the invite button', function () {
    $admin = User::create(['name' => 'Manager only', 'email' => 'manager-only@example.com', 'password' => 'secret-password']);
    $admin->assignRole(Role::findByName('editor', 'baobab'));
    app(GrantPermission::class)($admin, 'baobab.admin.access');
    app(GrantPermission::class)($admin, 'baobab.users.manage');

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee(route('admin.users.create'), false);
});

it('keeps the user list open to users.impersonate alone, without the invite button', function () {
    $actor = User::create(['name' => 'Impersonator only', 'email' => 'impersonator-only@example.com', 'password' => 'secret-password']);
    $actor->assignRole(Role::findByName('editor', 'baobab'));
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.users.impersonate');

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertDontSee(route('admin.users.create'), false);

    $this->get(route('admin.users.create'))->assertForbidden();
});

it('invites a user from the admin: pending account, unusable password, role, e-mail and audit', function () {
    Queue::fake();
    $admin = invitingAdmin();

    $response = $this->actingAs($admin, 'baobab')->post(route('admin.users.store'), [
        'name' => 'New Editor',
        'email' => 'new-editor@example.com',
        'role' => 'editor',
    ]);

    $invited = User::where('email', 'new-editor@example.com')->firstOrFail();
    $response->assertRedirect(route('admin.users.show', ['user' => $invited]));

    expect($invited->hasPendingInvitation())->toBeTrue()
        ->and($invited->hasRole('editor', 'baobab'))->toBeTrue()
        ->and(AuditEntry::where('action', 'user.invited')->where('auditable_id', $invited->id)->exists())->toBeTrue()
        ->and(invitationLink('new-editor@example.com'))->toContain('/invitation/');

    auth('baobab')->logout();
    $this->post('/login', ['email' => 'new-editor@example.com', 'password' => ''])->assertSessionHasErrors();
    $this->assertGuest('baobab');
});

it('only offers roles strictly below the actor on the invite form', function () {
    $admin = invitingAdmin();

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertSee('value="editor"', false)
        ->assertDontSee('value="admin"', false)
        ->assertDontSee('value="super-admin"', false);
});

it('refuses to invite with a role at or above the actor level', function () {
    Queue::fake();
    $admin = invitingAdmin();

    $this->actingAs($admin, 'baobab')
        ->post(route('admin.users.store'), ['name' => 'Peer', 'email' => 'peer@example.com', 'role' => 'admin'])
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'peer@example.com')->exists())->toBeFalse();
    Queue::assertNotPushed(SendQueuedMail::class);

    expect(fn () => app(InviteUser::class)($admin, 'Peer', 'peer@example.com', 'super-admin'))
        ->toThrow(HierarchyViolationException::class);
});

it('creates no account when the invitation e-mail cannot be sent (recette du 23 septembre 2026)', function () {
    config(['baobab.mail.templates' => collect(config('baobab.mail.templates'))
        ->reject(fn (array $template): bool => $template['key'] === 'core.user_invited')
        ->values()
        ->all()]);

    expect(fn () => app(InviteUser::class)(invitingAdmin(), 'Orphan', 'orphan@example.com', 'author'))
        ->toThrow(MailTemplateNotFoundException::class);

    expect(User::where('email', 'orphan@example.com')->exists())->toBeFalse();
});

it('refuses an e-mail address already used by an account', function () {
    $admin = invitingAdmin();

    expect(fn () => app(InviteUser::class)($admin, 'Twin', $admin->email, 'editor'))
        ->toThrow(ValidationException::class);
});

it('accepts the invitation: password chosen, invitation cleared, back to the login screen', function () {
    Queue::fake();
    $invited = app(InviteUser::class)(invitingAdmin(), 'Accepting', 'accepting@example.com', 'author');
    [$token, $email] = invitationTokenAndEmail(invitationLink('accepting@example.com'));

    $this->get(route('invitation.accept', ['token' => $token, 'email' => $email]))
        ->assertOk()
        ->assertSee(__('baobab::admin.auth.invitation_intro'));

    $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => $email,
        'password' => 'my-own-long-password',
        'password_confirmation' => 'my-own-long-password',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('baobab::admin.auth.invitation_accepted'));

    $this->assertGuest('baobab');

    $fresh = $invited->fresh();
    expect($fresh->hasPendingInvitation())->toBeFalse()
        ->and(Hash::check('my-own-long-password', $fresh->password))->toBeTrue()
        ->and(AuditEntry::where('action', 'user.invitation.accepted')->where('auditable_id', $invited->id)->exists())->toBeTrue();

    $this->post('/login', ['email' => 'accepting@example.com', 'password' => 'my-own-long-password'])
        ->assertRedirect(route('admin.dashboard'));
});

it('refuses an invitation link after 24 hours', function () {
    Queue::fake();
    app(InviteUser::class)(invitingAdmin(), 'Late', 'late@example.com', 'author');
    [$token, $email] = invitationTokenAndEmail(invitationLink('late@example.com'));

    $this->travel(25)->hours();

    $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => $email,
        'password' => 'my-own-long-password',
        'password_confirmation' => 'my-own-long-password',
    ])->assertSessionHasErrors(['email' => __('baobab::admin.auth.invitation_link_invalid')]);

    expect(User::where('email', 'late@example.com')->firstOrFail()->hasPendingInvitation())->toBeTrue();
});

it('enforces the password policy when accepting', function () {
    Queue::fake();
    app(InviteUser::class)(invitingAdmin(), 'Weak', 'weak-invitee@example.com', 'author');
    [$token, $email] = invitationTokenAndEmail(invitationLink('weak-invitee@example.com'));

    $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => $email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

it('resends a pending invitation from the user page, the old link no longer working', function () {
    Queue::fake();
    $admin = invitingAdmin();
    $invited = app(InviteUser::class)($admin, 'Resent', 'resent@example.com', 'author');
    [$oldToken] = invitationTokenAndEmail(invitationLink('resent@example.com'));

    Queue::fake();

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $invited]))
        ->assertSee(route('admin.users.invitation.resend', ['user' => $invited]), false);

    $this->post(route('admin.users.invitation.resend', ['user' => $invited]))
        ->assertRedirect(route('admin.users.show', ['user' => $invited]));

    [$newToken] = invitationTokenAndEmail(invitationLink('resent@example.com'));
    expect($newToken)->not->toBe($oldToken);

    auth('baobab')->logout();

    $this->post(route('invitation.store'), [
        'token' => $oldToken,
        'email' => 'resent@example.com',
        'password' => 'my-own-long-password',
        'password_confirmation' => 'my-own-long-password',
    ])->assertSessionHasErrors('email');
});

it('cancels a pending invitation from the user page: account deleted, link dead, audited', function () {
    Queue::fake();
    $admin = invitingAdmin();
    $invited = app(InviteUser::class)($admin, 'Cancelled', 'cancelled@example.com', 'author');
    [$token] = invitationTokenAndEmail(invitationLink('cancelled@example.com'));

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $invited]))
        ->assertSee(route('admin.users.invitation.cancel', ['user' => $invited]), false);

    $this->delete(route('admin.users.invitation.cancel', ['user' => $invited]))
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'cancelled@example.com')->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'cancelled@example.com')->exists())->toBeFalse()
        ->and(AuditEntry::where('action', 'user.invitation.cancelled')->exists())->toBeTrue();

    auth('baobab')->logout();

    $this->post(route('invitation.store'), [
        'token' => $token,
        'email' => 'cancelled@example.com',
        'password' => 'my-own-long-password',
        'password_confirmation' => 'my-own-long-password',
    ])->assertSessionHasErrors('email');
});

it('never cancels an account whose invitation was accepted', function () {
    $admin = invitingAdmin();
    $active = User::create(['name' => 'Active', 'email' => 'active-account@example.com', 'password' => 'secret-password']);
    $active->assignRole(Role::findByName('author', 'baobab'));

    expect(fn () => app(CancelUserInvitation::class)($admin, $active))->toThrow(InvitationNotPendingException::class);

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $active]))
        ->assertDontSee(route('admin.users.invitation.cancel', ['user' => $active]), false);

    expect($active->fresh())->not->toBeNull();
});

it('refuses to resend an invitation that was already accepted', function () {
    $user = User::create(['name' => 'Already', 'email' => 'already@example.com', 'password' => 'secret-password']);

    expect(fn () => app(SendUserInvitation::class)($user))->toThrow(InvitationNotPendingException::class);
});

it('clears a pending invitation when the invitee goes through forgotten password instead', function () {
    Queue::fake();
    $invited = app(InviteUser::class)(invitingAdmin(), 'Forgetful', 'forgetful@example.com', 'author');

    $this->post(route('password.update'), [
        'token' => Password::broker('baobab_users')->createToken($invited),
        'email' => 'forgetful@example.com',
        'password' => 'my-own-long-password',
        'password_confirmation' => 'my-own-long-password',
    ])->assertRedirect(route('login'));

    expect($invited->fresh()->hasPendingInvitation())->toBeFalse();
});
