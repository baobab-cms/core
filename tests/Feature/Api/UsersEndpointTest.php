<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

/**
 * `/api/v1/users` et `/api/v1/roles` (spec 05 §7, décision 5) : adaptateurs
 * des mêmes Actions que l'admin, erreurs RFC 9457.
 */
function usersApiAdmin(string $email = 'users-api-admin@example.com'): User
{
    $admin = User::create(['name' => 'Users API Admin', 'email' => $email, 'password' => 'secret-password']);
    $admin->assignRole(Role::findByName('admin', 'baobab'));

    return $admin;
}

/** @param  list<string>  $abilities */
function usersApiToken(User $user, array $abilities): string
{
    return app(CreateApiToken::class)($user, 'users-api', $abilities)->plainTextToken;
}

it('requires authentication', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
    $this->getJson('/api/v1/roles')->assertUnauthorized();
});

it('lists users without ever exposing a secret, filtered by role or pending invitation', function () {
    Queue::fake();
    $admin = usersApiAdmin();
    app(InviteUser::class)($admin, 'Pending Author', 'api-pending@example.com', 'author');
    $token = usersApiToken($admin, ['baobab.users.manage']);

    $response = $this->withToken($token)->getJson('/api/v1/users')->assertOk();

    expect($response->json('meta.pagination.total'))->toBe(2)
        ->and($response->getContent())->not->toContain('password')
        ->and($response->getContent())->not->toContain('two_factor')
        ->and($response->getContent())->not->toContain('remember_token');

    $this->withToken($token)->getJson('/api/v1/users?filter[pending]=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'api-pending@example.com')
        ->assertJsonPath('data.0.invitation_pending', true);

    $this->withToken($token)->getJson('/api/v1/users?filter[role]=admin')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', $admin->email);
});

it('refuses a token without users.manage nor users.impersonate', function () {
    $admin = usersApiAdmin('users-api-noability@example.com');
    app(GrantPermission::class)($admin, 'baobab.audit.view');
    $token = usersApiToken($admin, ['baobab.audit.view']);

    $this->withToken($token)->getJson('/api/v1/users')->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'author'])->assertForbidden();
});

it('invites through the API: 201, Location, e-mail sent', function () {
    Queue::fake();
    $admin = usersApiAdmin('users-api-invite@example.com');
    $token = usersApiToken($admin, ['baobab.users.manage']);

    $response = $this->withToken($token)
        ->postJson('/api/v1/users', ['name' => 'API Invitee', 'email' => 'api-invitee@example.com', 'role' => 'author'])
        ->assertCreated()
        ->assertJsonPath('data.invitation_pending', true)
        ->assertJsonPath('data.roles.0.name', 'author');

    $id = $response->json('data.id');
    expect($response->headers->get('Location'))->toEndWith("/api/v1/users/{$id}");

    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->templateKey === 'core.user_invited' && $mail->to === 'api-invitee@example.com');

    $this->withToken($token)
        ->getJson("/api/v1/users/{$id}")
        ->assertOk()
        ->assertJsonPath('data.email', 'api-invitee@example.com');
});

it('answers 403 problem+json on a hierarchy violation and 422 on a taken e-mail', function () {
    $admin = usersApiAdmin('users-api-errors@example.com');
    $token = usersApiToken($admin, ['baobab.users.manage']);

    $this->withToken($token)
        ->postJson('/api/v1/users', ['name' => 'Peer', 'email' => 'api-peer@example.com', 'role' => 'admin'])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');

    $this->withToken($token)
        ->postJson('/api/v1/users', ['name' => 'Twin', 'email' => $admin->email, 'role' => 'author'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('resends a pending invitation (202) and refuses an accepted one (409)', function () {
    Queue::fake();
    $admin = usersApiAdmin('users-api-resend@example.com');
    $token = usersApiToken($admin, ['baobab.users.manage']);
    $invited = app(InviteUser::class)($admin, 'Resend Me', 'api-resend@example.com', 'author');
    $accepted = User::create(['name' => 'Accepted', 'email' => 'api-accepted@example.com', 'password' => 'secret-password']);
    $accepted->assignRole(Role::findByName('author', 'baobab'));

    $this->withToken($token)->postJson("/api/v1/users/{$invited->id}/invitation")->assertStatus(202);
    $this->withToken($token)->postJson("/api/v1/users/{$accepted->id}/invitation")->assertStatus(409);
    $this->withToken($token)->postJson('/api/v1/users/999999/invitation')->assertNotFound();
});

it('cancels a pending invitation (204) and refuses an accepted one (409)', function () {
    Queue::fake();
    $admin = usersApiAdmin('users-api-cancel@example.com');
    $token = usersApiToken($admin, ['baobab.users.manage']);
    $invited = app(InviteUser::class)($admin, 'Cancel Me', 'api-cancel@example.com', 'author');

    $this->withToken($token)->deleteJson("/api/v1/users/{$invited->id}/invitation")->assertNoContent();
    expect(User::find($invited->id))->toBeNull();

    $this->withToken($token)->deleteJson("/api/v1/users/{$admin->id}/invitation")->assertStatus(409);
});

it('grants and revokes roles through the API', function () {
    $admin = usersApiAdmin('users-api-roles@example.com');
    $token = usersApiToken($admin, ['baobab.users.manage']);
    $author = User::create(['name' => 'API Author', 'email' => 'api-author@example.com', 'password' => 'secret-password']);
    $author->assignRole(Role::findByName('author', 'baobab'));

    $this->withToken($token)
        ->postJson("/api/v1/users/{$author->id}/roles", ['role' => 'editor'])
        ->assertOk()
        ->assertJsonPath('data.level', 60);

    $this->withToken($token)->deleteJson("/api/v1/users/{$author->id}/roles/editor")->assertNoContent();
    expect($author->fresh()->hasRole('editor', 'baobab'))->toBeFalse();

    $this->withToken($token)->postJson("/api/v1/users/{$author->id}/roles", ['role' => 'admin'])->assertForbidden();
    $this->withToken($token)->postJson("/api/v1/users/{$author->id}/roles", ['role' => 'no-such-role'])->assertNotFound();
});

it('lists roles read-only, flagging those the caller can assign', function () {
    $admin = usersApiAdmin('users-api-rolelist@example.com');

    $roles = collect($this->withToken(usersApiToken($admin, ['baobab.users.manage']))
        ->getJson('/api/v1/roles')
        ->assertOk()
        ->json('data'))->keyBy('name');

    expect($roles['editor']['assignable'])->toBeTrue()
        ->and($roles['admin']['assignable'])->toBeFalse()
        ->and($roles['super-admin']['assignable'])->toBeFalse();
});

it('documents the users and roles paths in the OpenAPI document', function () {
    $this->getJson('/api/v1/openapi.json')
        ->assertOk()
        ->assertJsonPath('paths./users.post.tags.0', 'users')
        ->assertJsonPath('paths./roles.get.tags.0', 'users')
        ->assertJsonPath('components.schemas.User.properties.invitation_pending.type', 'boolean');
});
