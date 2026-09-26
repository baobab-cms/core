<?php

use Baobab\Access\AccessManager;
use Baobab\Auth\Actions\CreateApiToken;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Users\Actions\DeactivateUser;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

/**
 * Désactivation d'un compte (spec 05 §5, décision 5 k) côté écrans et API :
 * adaptateurs minces de `DeactivateUser` et `ReactivateUser`.
 */
function statusUser(string $role, string $email): User
{
    $user = User::create(['name' => ucfirst($role).' '.$email, 'email' => $email, 'password' => 'secret-password']);
    $user->assignRole(Role::findByName($role, 'baobab'));

    return $user;
}

it('deactivates and reactivates from the user page, with an optional reason', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-admin@example.com');
    $author = statusUser('author', 'status-author@example.com');

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $author]))
        ->assertOk()
        ->assertSee(route('admin.users.deactivate', ['user' => $author]), false)
        ->assertDontSee(route('admin.users.reactivate', ['user' => $author]), false);

    $this->post(route('admin.users.deactivate', ['user' => $author]), ['reason' => 'Left the company'])
        ->assertRedirect(route('admin.users.show', ['user' => $author]))
        ->assertSessionHas('toast.type', 'success');

    expect($author->fresh()->isDeactivated())->toBeTrue();

    $this->get(route('admin.users.show', ['user' => $author]))
        ->assertOk()
        ->assertSee(__('baobab::admin.users.status.reason_shown', ['reason' => 'Left the company']))
        ->assertSee(route('admin.users.reactivate', ['user' => $author]), false)
        ->assertDontSee(route('admin.users.deactivate', ['user' => $author]), false);

    $this->post(route('admin.users.reactivate', ['user' => $author]))
        ->assertRedirect(route('admin.users.show', ['user' => $author]))
        ->assertSessionHas('toast.type', 'success');

    expect($author->fresh()->isDeactivated())->toBeFalse();
    Queue::assertPushed(SendQueuedMail::class, fn (SendQueuedMail $mail): bool => $mail->templateKey === 'core.password_reset' && $mail->to === $author->email);
});

it('refuses a reason longer than the limit from the user page', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-admin-long@example.com');
    $author = statusUser('author', 'status-author-long@example.com');

    $this->actingAs($admin, 'baobab')
        ->post(route('admin.users.deactivate', ['user' => $author]), ['reason' => str_repeat('x', 501)])
        ->assertSessionHasErrors('reason');

    expect($author->fresh()->isDeactivated())->toBeFalse();
});

it('tells the admin when the deactivation is refused', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-admin-refused@example.com');
    $peer = statusUser('admin', 'status-peer@example.com');
    $author = statusUser('author', 'status-author-refused@example.com');
    $this->actingAs($admin, 'baobab');

    $this->post(route('admin.users.deactivate', ['user' => $peer]))
        ->assertRedirect(route('admin.users.show', ['user' => $peer]))
        ->assertSessionHas('toast.type', 'error');

    $this->post(route('admin.users.deactivate', ['user' => $admin]))
        ->assertSessionHas('toast.type', 'error');

    app(DeactivateUser::class)($admin, $author);

    $this->post(route('admin.users.deactivate', ['user' => $author]))
        ->assertSessionHas('toast.message', __('baobab::admin.users.status.not_active'));

    $this->post(route('admin.users.reactivate', ['user' => $peer]))
        ->assertSessionHas('toast.message', __('baobab::admin.users.status.not_deactivated'));

    expect($peer->fresh()->isDeactivated())->toBeFalse();
});

it('reserves the deactivation to users.manage', function () {
    $author = statusUser('author', 'status-author-forbidden@example.com');
    $other = statusUser('author', 'status-other-forbidden@example.com');

    $this->actingAs($author, 'baobab')
        ->post(route('admin.users.deactivate', ['user' => $other]))
        ->assertForbidden();

    $this->post(route('admin.users.reactivate', ['user' => $other]))->assertForbidden();
});

it('offers no deactivation on a pending invitation or on yourself', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-admin-offer@example.com');
    $invited = app(InviteUser::class)($admin, 'Invited', 'status-invited@example.com', 'author');

    $this->actingAs($admin, 'baobab')
        ->get(route('admin.users.show', ['user' => $invited]))
        ->assertOk()
        ->assertDontSee(route('admin.users.deactivate', ['user' => $invited]), false);

    $this->get(route('admin.users.show', ['user' => $admin]))
        ->assertOk()
        ->assertDontSee(route('admin.users.deactivate', ['user' => $admin]), false);
});

it('shows a status badge per account and filters the list by status', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-admin-list@example.com');
    $active = statusUser('author', 'status-active@example.com');
    $deactivated = statusUser('author', 'status-deactivated@example.com');
    app(InviteUser::class)($admin, 'Pending', 'status-pending@example.com', 'author');
    app(DeactivateUser::class)($admin, $deactivated);

    $this->actingAs($admin, 'baobab');

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('status-active@example.com')
        ->assertSee('status-deactivated@example.com')
        ->assertSee('status-pending@example.com')
        ->assertSee(__('baobab::admin.users.status.deactivated_badge'))
        ->assertSee(__('baobab::admin.users.invite.pending_badge'));

    $this->get(route('admin.users.index', ['status' => 'deactivated']))
        ->assertOk()
        ->assertSee('status-deactivated@example.com')
        ->assertDontSee('status-active@example.com')
        ->assertDontSee('status-pending@example.com');

    $this->get(route('admin.users.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('status-active@example.com')
        ->assertDontSee('status-deactivated@example.com')
        ->assertDontSee('status-pending@example.com');

    $this->get(route('admin.users.index', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('status-pending@example.com')
        ->assertDontSee('status-active@example.com')
        ->assertDontSee('status-deactivated@example.com');

    // Une valeur inconnue retombe sur « tous ».
    $this->get(route('admin.users.index', ['status' => 'nonsense']))
        ->assertOk()
        ->assertSee('status-deactivated@example.com')
        ->assertSee('status-active@example.com');

    expect($active->fresh()->isActive())->toBeTrue();
});

it('offers no impersonation of a deactivated account', function () {
    Queue::fake();
    $owner = app(AccessManager::class)->createRole('status-owner', 120);
    $actor = User::create(['name' => 'Owner', 'email' => 'status-owner@example.com', 'password' => 'secret-password']);
    $actor->assignRole($owner);
    $superAdmin = statusUser('super-admin', 'status-super@example.com');
    $author = statusUser('author', 'status-author-impersonate@example.com');
    app(DeactivateUser::class)($actor, $author);

    $this->actingAs($superAdmin, 'baobab')
        ->get(route('admin.users.show', ['user' => $author]))
        ->assertOk()
        ->assertDontSee(route('admin.users.impersonate', ['user' => $author]), false);
});

/** @return array<string, string> */
function statusApiHeaders(User $actor): array
{
    return ['Authorization' => 'Bearer '.app(CreateApiToken::class)($actor, 'status-api', ['baobab.users.manage'])->plainTextToken];
}

it('deactivates and reactivates through the api and returns the user', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-api-admin@example.com');
    $author = statusUser('author', 'status-api-author@example.com');
    $headers = statusApiHeaders($admin);

    $this->postJson("/api/v1/users/{$author->id}/deactivate", ['reason' => 'Left the company'], $headers)
        ->assertOk()
        ->assertJsonPath('data.deactivated', true)
        ->assertJsonPath('data.deactivation_reason', 'Left the company')
        ->assertJsonPath('data.email', $author->email);

    expect($author->fresh()->isDeactivated())->toBeTrue();

    $this->getJson('/api/v1/users?filter[deactivated]=1', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', $author->email);

    $this->getJson('/api/v1/users?filter[deactivated]=0', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', $admin->email);

    $this->postJson("/api/v1/users/{$author->id}/reactivate", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.deactivated', false)
        ->assertJsonPath('data.deactivation_reason', null);

    expect($author->fresh()->isDeactivated())->toBeFalse();
});

it('answers 403 for a refused hierarchy, 409 for a state without object and 422 for a long reason', function () {
    Queue::fake();
    $admin = statusUser('admin', 'status-api-admin-errors@example.com');
    $peer = statusUser('admin', 'status-api-peer@example.com');
    $author = statusUser('author', 'status-api-author-errors@example.com');
    $invited = app(InviteUser::class)($admin, 'Invited', 'status-api-invited@example.com', 'author');
    $headers = statusApiHeaders($admin);

    $this->postJson("/api/v1/users/{$peer->id}/deactivate", [], $headers)->assertForbidden();
    $this->postJson("/api/v1/users/{$admin->id}/deactivate", [], $headers)->assertForbidden();
    $this->postJson("/api/v1/users/{$invited->id}/deactivate", [], $headers)->assertStatus(409);
    $this->postJson("/api/v1/users/{$author->id}/reactivate", [], $headers)->assertStatus(409);
    $this->postJson("/api/v1/users/{$author->id}/deactivate", ['reason' => str_repeat('x', 501)], $headers)->assertUnprocessable();
    $this->postJson('/api/v1/users/999999/deactivate', [], $headers)->assertNotFound();

    expect($author->fresh()->isDeactivated())->toBeFalse();

    $this->postJson("/api/v1/users/{$author->id}/deactivate", [], $headers)->assertOk();
    $this->postJson("/api/v1/users/{$author->id}/deactivate", [], $headers)->assertStatus(409);
});

it('requires authentication and users.manage on the deactivation endpoints', function () {
    $author = statusUser('author', 'status-api-author-auth@example.com');
    $other = statusUser('author', 'status-api-other-auth@example.com');

    $this->postJson("/api/v1/users/{$other->id}/deactivate")->assertUnauthorized();
    $this->postJson("/api/v1/users/{$other->id}/reactivate")->assertUnauthorized();

    $token = app(CreateApiToken::class)($author, 'no-manage', [])->plainTextToken;

    $this->postJson("/api/v1/users/{$other->id}/deactivate", [], ['Authorization' => 'Bearer '.$token])->assertForbidden();
});

it('documents the deactivation endpoints and the user fields in the OpenAPI document', function () {
    $document = json_decode((string) file_get_contents(__DIR__.'/../../../resources/openapi/openapi-core.json'), true);

    expect($document['paths'])->toHaveKeys(['/users/{user}/deactivate', '/users/{user}/reactivate'])
        ->and($document['components']['schemas']['User']['properties'])->toHaveKeys(['deactivated', 'deactivated_at', 'deactivation_reason']);
});
