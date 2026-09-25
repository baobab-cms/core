<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Mail\Exceptions\MailTemplateNotFoundException;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Users\Actions\ConfirmProfileChange;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Actions\RequestProfileChange;
use Baobab\Users\Exceptions\ProfileChangeLinkInvalidException;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\Models\User;
use Baobab\Users\ProfileChangeOutcome;
use Baobab\Users\ProfileChangeStage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Changement de nom ou d'e-mail (spec 05 §5, décision 5 f-g et j) : validé par
 * l'adresse actuelle, puis pour un e-mail confirmé par la nouvelle, en
 * séquence ; une seule demande en attente par compte ; exception « boîte
 * perdue » encadrée ; jamais sur un compte invité.
 */
function profileUser(string $email, string $role = 'editor', string $name = 'Profile Person'): User
{
    $user = User::create(['name' => $name, 'email' => $email, 'password' => 'secret-password']);
    $user->assignRole(Role::findByName($role, 'baobab'));
    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

/** Le lien du template `$key` reçu par `$to`, tel qu'il le recevrait. */
function profileLink(string $key, string $to): string
{
    $link = null;

    Queue::assertPushed(SendQueuedMail::class, function (SendQueuedMail $mail) use ($key, $to, &$link): bool {
        if ($mail->templateKey !== $key || $mail->to !== $to) {
            return false;
        }

        preg_match('/href="([^"]+)"/', $mail->html, $matches);
        $link = html_entity_decode($matches[1] ?? '');

        return true;
    });

    return (string) $link;
}

function profileToken(string $link): string
{
    preg_match('#/profile-change/(.+)$#', $link, $matches);

    return $matches[1];
}

function profileSession(string $id, User $user): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
}

it('declares the three templates with a required link', function () {
    foreach (['core.email_change_verify', 'core.email_change_confirm'] as $key) {
        $declaration = collect(config('baobab.mail.templates'))->firstWhere('key', $key);

        expect($declaration['variables']['confirm_url']['required'])->toBeTrue();
    }

    expect(collect(config('baobab.mail.templates'))->pluck('key'))->toContain('core.email_change_warning');
});

it('changes nothing until the current address validates, and writes to that address only', function () {
    Queue::fake();
    $user = profileUser('profile-old@example.com');

    $request = app(RequestProfileChange::class)($user, $user, 'New Name', 'profile-new@example.com');

    $user->refresh();
    expect($user->name)->toBe('Profile Person')
        ->and($user->email)->toBe('profile-old@example.com')
        ->and($request->stage)->toBe(ProfileChangeStage::Verify)
        ->and($request->forced)->toBeFalse()
        ->and($request->token_hash)->not->toContain('profile-change')
        ->and(profileLink('core.email_change_verify', 'profile-old@example.com'))->toContain('/profile-change/');

    Queue::assertPushed(SendQueuedMail::class, 1);
    Queue::assertNotPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->to === 'profile-new@example.com');

    expect(AuditEntry::where('action', 'user.profile.change_requested')->where('auditable_id', $user->id)->exists())->toBeTrue();
});

it('confirms an e-mail change in sequence: the new address only hears once the old one has validated', function () {
    Queue::fake();
    $user = profileUser('seq-old@example.com');
    profileSession('seq-current', $user);
    profileSession('seq-other', $user);
    $user->forceFill(['remember_token' => 'old-remember'])->save();

    app(RequestProfileChange::class)($user, $user, null, 'seq-new@example.com');
    $verifyToken = profileToken(profileLink('core.email_change_verify', 'seq-old@example.com'));

    $outcome = app(ConfirmProfileChange::class)($verifyToken, 'seq-current');

    expect($outcome)->toBe(ProfileChangeOutcome::AwaitingNewAddress)
        ->and($user->fresh()->email)->toBe('seq-old@example.com')
        ->and(ProfileChangeRequest::where('user_id', $user->id)->firstOrFail()->stage)->toBe(ProfileChangeStage::Confirm);

    // Le lien de l'adresse actuelle ne vaut plus une fois utilisé.
    expect(fn () => app(ConfirmProfileChange::class)($verifyToken))->toThrow(ProfileChangeLinkInvalidException::class);

    $confirmToken = profileToken(profileLink('core.email_change_confirm', 'seq-new@example.com'));
    expect($confirmToken)->not->toBe($verifyToken);

    $outcome = app(ConfirmProfileChange::class)($confirmToken, 'seq-current');
    $user->refresh();

    expect($outcome)->toBe(ProfileChangeOutcome::Applied)
        ->and($user->email)->toBe('seq-new@example.com')
        ->and($user->remember_token)->not->toBe('old-remember')
        ->and(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe(['seq-current'])
        ->and(ProfileChangeRequest::where('user_id', $user->id)->exists())->toBeFalse();

    $entry = AuditEntry::where('action', 'user.profile.changed')->where('auditable_id', $user->id)->firstOrFail();
    expect($entry->data['before']['email'])->toBe('seq-old@example.com')
        ->and($entry->data['after']['email'])->toBe('seq-new@example.com');
});

it('keeps the password and the two-factor secret when the e-mail changes', function () {
    Queue::fake();
    $user = profileUser('keep-old@example.com');
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    app(RequestProfileChange::class)($user, $user, null, 'keep-new@example.com');
    app(ConfirmProfileChange::class)(profileToken(profileLink('core.email_change_verify', 'keep-old@example.com')));
    app(ConfirmProfileChange::class)(profileToken(profileLink('core.email_change_confirm', 'keep-new@example.com')));

    $user->refresh();
    expect(Hash::check('secret-password', $user->password))->toBeTrue()
        ->and($user->hasTwoFactorEnabled())->toBeTrue();
});

it('applies a name-only change after a single confirmation, without touching sessions', function () {
    Queue::fake();
    $user = profileUser('name-only@example.com');
    profileSession('name-other', $user);

    app(RequestProfileChange::class)($user, $user, 'Renamed Person', null);
    $outcome = app(ConfirmProfileChange::class)(profileToken(profileLink('core.email_change_verify', 'name-only@example.com')));

    expect($outcome)->toBe(ProfileChangeOutcome::Applied)
        ->and($user->fresh()->name)->toBe('Renamed Person')
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    Queue::assertPushed(SendQueuedMail::class, 1);
});

it('lets a new request replace the previous one and kills its link', function () {
    Queue::fake();
    $user = profileUser('replace-old@example.com');

    app(RequestProfileChange::class)($user, $user, null, 'replace-first@example.com');
    $firstToken = profileToken(profileLink('core.email_change_verify', 'replace-old@example.com'));

    Queue::fake();
    app(RequestProfileChange::class)($user, $user, null, 'replace-second@example.com');
    $secondToken = profileToken(profileLink('core.email_change_verify', 'replace-old@example.com'));

    expect(ProfileChangeRequest::where('user_id', $user->id)->count())->toBe(1)
        ->and(ProfileChangeRequest::where('user_id', $user->id)->firstOrFail()->new_email)->toBe('replace-second@example.com')
        ->and(fn () => app(ConfirmProfileChange::class)($firstToken))->toThrow(ProfileChangeLinkInvalidException::class)
        ->and(app(ConfirmProfileChange::class)($secondToken))->toBe(ProfileChangeOutcome::AwaitingNewAddress);
});

it('refuses an expired link', function () {
    Queue::fake();
    $user = profileUser('expired@example.com');

    app(RequestProfileChange::class)($user, $user, 'Late Name', null);
    $token = profileToken(profileLink('core.email_change_verify', 'expired@example.com'));

    $this->travel(25)->hours();

    expect(fn () => app(ConfirmProfileChange::class)($token))->toThrow(ProfileChangeLinkInvalidException::class)
        ->and($user->fresh()->name)->toBe('Profile Person');
});

it('drops the request when the address was taken in the meantime', function () {
    Queue::fake();
    $user = profileUser('race-old@example.com');

    app(RequestProfileChange::class)($user, $user, null, 'race-new@example.com');
    app(ConfirmProfileChange::class)(profileToken(profileLink('core.email_change_verify', 'race-old@example.com')));
    $confirmToken = profileToken(profileLink('core.email_change_confirm', 'race-new@example.com'));

    profileUser('race-new@example.com', 'editor', 'Someone Else');

    expect(fn () => app(ConfirmProfileChange::class)($confirmToken))->toThrow(ValidationException::class)
        ->and($user->fresh()->email)->toBe('race-old@example.com')
        ->and(ProfileChangeRequest::where('user_id', $user->id)->exists())->toBeFalse();
});

it('validates what is asked', function (?string $name, ?string $email, string $errorKey) {
    Queue::fake();
    $user = profileUser('rules-old@example.com');
    profileUser('rules-taken@example.com', 'editor', 'Taken');

    expect(fn () => app(RequestProfileChange::class)($user, $user, $name, $email))->toThrow(ValidationException::class);

    try {
        app(RequestProfileChange::class)($user, $user, $name, $email);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($errorKey);
    }

    Queue::assertNothingPushed();
    expect(ProfileChangeRequest::count())->toBe(0);
})->with([
    'empty name' => ['', null, 'name'],
    'invalid e-mail' => [null, 'not-an-email', 'email'],
    'e-mail of another account' => [null, 'rules-taken@example.com', 'email'],
    'nothing to change (same values)' => ['Profile Person', 'RULES-OLD@example.com', 'name'],
    'nothing at all' => [null, null, 'name'],
]);

it('creates no request when the e-mail cannot be sent', function () {
    Queue::fake();
    $user = profileUser('nosend@example.com');
    config(['baobab.mail.templates' => collect(config('baobab.mail.templates'))
        ->reject(fn (array $template): bool => $template['key'] === 'core.email_change_verify')->values()->all()]);

    expect(fn () => app(RequestProfileChange::class)($user, $user, 'Name', null))->toThrow(MailTemplateNotFoundException::class);
    expect(ProfileChangeRequest::count())->toBe(0);
});

it('refuses an e-mail change on a pending invitation, but lets the name through', function () {
    Queue::fake();
    $admin = profileUser('inv-admin@example.com', 'admin');
    $invited = app(InviteUser::class)($admin, 'Invited Person', 'invited-old@example.com', 'editor');

    expect(fn () => app(RequestProfileChange::class)($admin, $invited, null, 'invited-new@example.com'))
        ->toThrow(ValidationException::class);

    app(RequestProfileChange::class)($admin, $invited, 'Corrected Name', null);
    expect(ProfileChangeRequest::where('user_id', $invited->id)->firstOrFail()->new_name)->toBe('Corrected Name');
});

it('only lets a user manager act on another account, strictly below their level', function () {
    Queue::fake();
    $editor = profileUser('auth-editor@example.com');
    $other = profileUser('auth-other@example.com', 'author');
    $admin = profileUser('auth-admin@example.com', 'admin');
    $peer = profileUser('auth-peer@example.com', 'admin');

    expect(fn () => app(RequestProfileChange::class)($editor, $other, 'Hacked', null))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RequestProfileChange::class)($admin, $peer, 'Peer Rename', null))->toThrow(HierarchyViolationException::class);

    app(RequestProfileChange::class)($admin, $other, 'Author Renamed', null);

    // Le lien part à l'adresse du compte concerné, jamais à celle de l'admin.
    expect(profileLink('core.email_change_verify', 'auth-other@example.com'))->toContain('/profile-change/');
});

it('never lets a user take the lost-mailbox path on their own account', function () {
    Queue::fake();
    $admin = profileUser('own-lost@example.com', 'admin');

    expect(fn () => app(RequestProfileChange::class)($admin, $admin, null, 'own-lost-new@example.com', true, 'secret-password', 'because'))
        ->toThrow(AuthorizationException::class);
});

it('runs the lost-mailbox path: password, justification, new address alone, old address warned, every session closed', function () {
    Queue::fake();
    $admin = profileUser('lost-admin@example.com', 'admin', 'Lost Admin');
    $user = profileUser('lost-old@example.com');
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    profileSession('lost-a', $user);
    profileSession('lost-b', $user);

    $request = app(RequestProfileChange::class)($admin, $user, null, 'lost-new@example.com', true, 'secret-password', 'Mailbox closed, confirmed by phone');

    expect($request->stage)->toBe(ProfileChangeStage::Confirm)
        ->and($request->forced)->toBeTrue()
        ->and($user->fresh()->email)->toBe('lost-old@example.com');

    Queue::assertNotPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->templateKey === 'core.email_change_verify');
    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->templateKey === 'core.email_change_warning'
        && $mail->to === 'lost-old@example.com'
        && str_contains($mail->html, 'lost-new@example.com')
        && str_contains($mail->html, 'Lost Admin')
        && ! str_contains($mail->html, 'Mailbox closed'));

    $token = profileToken(profileLink('core.email_change_confirm', 'lost-new@example.com'));

    // Même si le confirmant a une session, « boîte perdue » les ferme toutes.
    $outcome = app(ConfirmProfileChange::class)($token, 'lost-a');
    $user->refresh();

    expect($outcome)->toBe(ProfileChangeOutcome::Applied)
        ->and($user->email)->toBe('lost-new@example.com')
        ->and($user->hasTwoFactorEnabled())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    foreach (['user.profile.change_requested', 'user.profile.changed'] as $action) {
        expect(AuditEntry::where('action', $action)->where('auditable_id', $user->id)->firstOrFail()->data['justification'])
            ->toBe('Mailbox closed, confirmed by phone');
    }
});

it('refuses the lost-mailbox path without the right password, a justification, or an e-mail-only request', function (string $password, string $justification, ?string $name, string $errorKey) {
    Queue::fake();
    $admin = profileUser('lostbad-admin@example.com', 'admin');
    $user = profileUser('lostbad-old@example.com');

    try {
        app(RequestProfileChange::class)($admin, $user, $name, 'lostbad-new@example.com', true, $password, $justification);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($errorKey);
    }

    Queue::assertNothingPushed();
    expect(ProfileChangeRequest::count())->toBe(0);
})->with([
    'wrong password' => ['not-the-password', 'reason', null, 'admin_password'],
    'no justification' => ['secret-password', '   ', null, 'justification'],
    'name together with the e-mail' => ['secret-password', 'reason', 'Other Name', 'email'],
]);

it('shows a confirmation page on GET without applying anything, and applies on POST', function () {
    Queue::fake();
    $user = profileUser('http-old@example.com');

    app(RequestProfileChange::class)($user, $user, 'Http Renamed', null);
    $link = profileLink('core.email_change_verify', 'http-old@example.com');

    $this->get($link)->assertOk()->assertSee('Http Renamed')->assertSee(route('profile-change.confirm'), false);
    expect($user->fresh()->name)->toBe('Profile Person');

    $this->post(route('profile-change.confirm'), ['token' => profileToken($link)])
        ->assertOk()
        ->assertSee(__('baobab::admin.auth.profile_change_applied'));

    expect($user->fresh()->name)->toBe('Http Renamed');

    $this->get($link)->assertOk()->assertSee(__('baobab::admin.auth.profile_change_invalid'));
    $this->post(route('profile-change.confirm'), ['token' => 'unknown'])->assertOk()->assertSee(__('baobab::admin.auth.profile_change_invalid'));
});

it('keeps the confirming owner\'s session only when the owner is the one confirming', function () {
    Queue::fake();
    $user = profileUser('keepsession-old@example.com');
    profileSession('keep-elsewhere', $user);

    app(RequestProfileChange::class)($user, $user, null, 'keepsession-new@example.com');
    $this->post(route('profile-change.confirm'), ['token' => profileToken(profileLink('core.email_change_verify', 'keepsession-old@example.com'))]);
    $this->post(route('profile-change.confirm'), ['token' => profileToken(profileLink('core.email_change_confirm', 'keepsession-new@example.com'))])
        ->assertSee(__('baobab::admin.auth.profile_change_applied'));

    // Confirmé hors de la session du titulaire (autre navigateur) : aucune n'est épargnée.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

it('shows the Profile screen in Mon compte and requests a change from it', function () {
    Queue::fake();
    $user = profileUser('screen-old@example.com');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.account.profile.show'))
        ->assertOk()
        ->assertSee('screen-old@example.com')
        ->assertSee(route('admin.account.profile.update'), false);

    $this->actingAs($user, 'baobab')
        ->put(route('admin.account.profile.update'), ['name' => 'Screen Renamed', 'email' => 'screen-old@example.com'])
        ->assertRedirect(route('admin.account.profile.show'));

    expect(profileLink('core.email_change_verify', 'screen-old@example.com'))->toContain('/profile-change/');

    $this->get(route('admin.account.profile.show'))
        ->assertOk()
        ->assertSee(__('baobab::admin.account.profile.pending_verify', ['email' => 'screen-old@example.com']));

    $this->put(route('admin.account.profile.update'), ['name' => 'Profile Person', 'email' => 'screen-old@example.com'])
        ->assertSessionHasErrors('name');
});

it('links to the Profile screen from the account menu', function () {
    $user = profileUser('menu@example.com');

    $this->actingAs($user, 'baobab')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.account.profile.show'), false);
});

it('blocks the Profile screen while impersonating', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');

    $this->actingAs($actor, 'baobab')->post("/admin/users/{$target->id}/impersonate");

    $this->put(route('admin.account.profile.update'), ['name' => 'Taken Over', 'email' => 'taken-over@example.com'])->assertForbidden();
});

it('offers the profile card on the user sheet to a manager, never on their own sheet nor a peer\'s', function () {
    $admin = profileUser('sheet-admin@example.com', 'admin');
    $editor = profileUser('sheet-editor@example.com');
    $peer = profileUser('sheet-peer@example.com', 'admin');

    $this->actingAs($admin, 'baobab')->get(route('admin.users.show', ['user' => $editor]))
        ->assertOk()
        ->assertSee(route('admin.users.profile.update', ['user' => $editor]), false);

    $this->get(route('admin.users.show', ['user' => $admin]))->assertOk()->assertDontSee('profile_admin_password', false);
    $this->get(route('admin.users.show', ['user' => $peer]))->assertOk()->assertDontSee('profile_admin_password', false);
});

it('requests a change from the user sheet, ordinary then lost-mailbox', function () {
    Queue::fake();
    $admin = profileUser('sheetreq-admin@example.com', 'admin');
    $editor = profileUser('sheetreq-editor@example.com');

    $this->actingAs($admin, 'baobab')
        ->put(route('admin.users.profile.update', ['user' => $editor]), ['name' => 'Editor Renamed', 'email' => 'sheetreq-editor@example.com'])
        ->assertRedirect(route('admin.users.show', ['user' => $editor]));

    expect(profileLink('core.email_change_verify', 'sheetreq-editor@example.com'))->toContain('/profile-change/');

    $this->put(route('admin.users.profile.update', ['user' => $editor]), [
        'name' => 'Editor Renamed',
        'email' => 'sheetreq-new@example.com',
        'lost_mailbox' => '1',
        'admin_password' => 'wrong',
        'justification' => 'lost',
    ])->assertSessionHasErrors('admin_password', errorBag: 'updateProfile');

    $this->put(route('admin.users.profile.update', ['user' => $editor]), [
        'name' => 'Profile Person',
        'email' => 'sheetreq-new@example.com',
        'lost_mailbox' => '1',
        'admin_password' => 'secret-password',
        'justification' => 'Phone call with the owner',
    ])->assertRedirect(route('admin.users.show', ['user' => $editor]));

    expect(ProfileChangeRequest::where('user_id', $editor->id)->firstOrFail()->forced)->toBeTrue();
});

it('refuses the sheet edit on a peer, and to a user without users.manage', function () {
    $admin = profileUser('sheetdeny-admin@example.com', 'admin');
    $peer = profileUser('sheetdeny-peer@example.com', 'admin');
    $editor = profileUser('sheetdeny-editor@example.com');
    $other = profileUser('sheetdeny-other@example.com', 'author');

    $this->actingAs($admin, 'baobab')
        ->put(route('admin.users.profile.update', ['user' => $peer]), ['name' => 'Nope', 'email' => 'sheetdeny-peer@example.com'])
        ->assertForbidden();

    $this->actingAs($editor, 'baobab')
        ->put(route('admin.users.profile.update', ['user' => $other]), ['name' => 'Nope', 'email' => 'sheetdeny-other@example.com'])
        ->assertForbidden();
});

/** @param  list<string>  $abilities */
function profileApiToken(User $user, array $abilities): string
{
    return app(CreateApiToken::class)($user, 'profile-api', $abilities)->plainTextToken;
}

it('requires authentication on PATCH /me and /users/{id}', function () {
    $this->patchJson('/api/v1/me', ['name' => 'X'])->assertUnauthorized();
    $this->patchJson('/api/v1/users/1', ['name' => 'X'])->assertUnauthorized();
});

it('accepts a change on PATCH /me with 202 and changes nothing yet', function () {
    Queue::fake();
    $user = profileUser('api-me@example.com');
    $token = profileApiToken($user, []);

    $response = $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'Api Renamed', 'email' => 'api-me-new@example.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.stage', 'verify')
        ->assertJsonPath('data.new_email', 'api-me-new@example.com');

    expect($response->getContent())->not->toContain('token')
        ->and($user->fresh()->name)->toBe('Profile Person')
        ->and(profileLink('core.email_change_verify', 'api-me@example.com'))->toContain('/profile-change/');

    $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'Profile Person'])->assertStatus(422);
    $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'X', 'lost_mailbox' => true, 'admin_password' => 'secret-password', 'justification' => 'x'])->assertForbidden();
});

it('lets a manager patch another user through the API, with the lost-mailbox path', function () {
    Queue::fake();
    $admin = profileUser('api-admin@example.com', 'admin');
    $editor = profileUser('api-editor@example.com');
    $peer = profileUser('api-peer@example.com', 'admin');
    $token = profileApiToken($admin, ['baobab.users.manage']);

    $this->withToken($token)->patchJson("/api/v1/users/{$editor->id}", ['name' => 'Api Editor'])
        ->assertStatus(202)
        ->assertJsonPath('data.stage', 'verify');

    $this->withToken($token)->patchJson("/api/v1/users/{$editor->id}", [
        'email' => 'api-editor-new@example.com',
        'lost_mailbox' => true,
        'admin_password' => 'secret-password',
        'justification' => 'Owner lost the mailbox',
    ])->assertStatus(202)->assertJsonPath('data.stage', 'confirm')->assertJsonPath('data.lost_mailbox', true);

    $this->withToken($token)->patchJson("/api/v1/users/{$peer->id}", ['name' => 'Peer'])->assertForbidden();
    $this->withToken($token)->patchJson('/api/v1/users/999999', ['name' => 'Ghost'])->assertNotFound();
});

it('documents PATCH /me and PATCH /users/{user} in the OpenAPI spec', function () {
    $paths = $this->getJson('/api/v1/openapi.json')->assertOk()->json('paths');

    expect($paths)->toHaveKey('/me')
        ->and($paths['/me'])->toHaveKey('patch')
        ->and($paths['/users/{user}'])->toHaveKey('patch');
});
