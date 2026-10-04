<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Auth\Actions\ListActiveSessions;
use Baobab\Auth\Actions\RevokeSession;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function insertTestSession(string $id, ?int $userId, int $lastActivity, string $ip = '127.0.0.1'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => $ip,
        'user_agent' => 'Test Agent',
        'payload' => base64_encode(serialize([])),
        'last_activity' => $lastActivity,
    ]);
}

it('resolves the baobab guard as the auth default, so sessions.user_id populates for it', function () {
    expect(config('auth.defaults.guard'))->toBe('baobab');
});

// ── Actions ──────────────────────────────────────────────────────────────────

it('lists a user active sessions ordered by last activity, scoped to that user', function () {
    $user = User::create(['name' => 'Target', 'email' => 'target-sessions@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'Other', 'email' => 'other-sessions@example.com', 'password' => 'secret']);

    insertTestSession('sess-old', $user->id, now()->subHour()->timestamp);
    insertTestSession('sess-new', $user->id, now()->timestamp);
    insertTestSession('sess-other-user', $other->id, now()->timestamp);

    $sessions = app(ListActiveSessions::class)($user);

    expect($sessions)->toHaveCount(2)
        ->and($sessions->pluck('id')->all())->toBe(['sess-new', 'sess-old']);
});

it('revokes only the targeted session for the targeted user', function () {
    $user = User::create(['name' => 'Target', 'email' => 'target-sessions2@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'Other', 'email' => 'other-sessions2@example.com', 'password' => 'secret']);

    insertTestSession('sess-a', $user->id, now()->timestamp);
    insertTestSession('sess-b', $other->id, now()->timestamp);

    app(RevokeSession::class)($user, 'sess-a');

    expect(DB::table('sessions')->where('id', 'sess-a')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sess-b')->exists())->toBeTrue();
});

it('does not revoke a session belonging to a different user even with the right session id', function () {
    $user = User::create(['name' => 'Target', 'email' => 'target-sessions3@example.com', 'password' => 'secret']);
    $other = User::create(['name' => 'Other', 'email' => 'other-sessions3@example.com', 'password' => 'secret']);

    insertTestSession('sess-cross', $other->id, now()->timestamp);

    app(RevokeSession::class)($user, 'sess-cross');

    expect(DB::table('sessions')->where('id', 'sess-cross')->exists())->toBeTrue();
});

// ── Écran ────────────────────────────────────────────────────────────────────

it('shows active sessions and the revoke button on the user fiche when the actor outranks the target', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-sessions@example.com', 'password' => 'secret']);
    $actor->assignRole(Role::findByName('admin', 'baobab'));
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.users.impersonate');

    $target = User::create(['name' => 'Target', 'email' => 'target-sessions4@example.com', 'password' => 'secret']);
    insertTestSession('sess-fiche', $target->id, now()->timestamp, '10.0.0.5');

    $this->actingAs($actor, 'baobab')
        ->get("/admin/users/{$target->id}")
        ->assertOk()
        ->assertSee('10.0.0.5')
        ->assertSee(__('baobab::admin.users.show.session_revoke_action'))
        ->assertSee(__('baobab::admin.users.show.session_revoke_confirm_title'))
        ->assertSee(__('baobab::admin.users.show.session_revoke_confirm_description'));
});

it('hides the revoke button on the fiche when viewing a peer of the same level', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-sessions2@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.users.impersonate');

    $peer = User::create(['name' => 'Peer', 'email' => 'peer-sessions@example.com', 'password' => 'secret']);
    insertTestSession('sess-peer', $peer->id, now()->timestamp, '10.0.0.6');

    $this->actingAs($actor, 'baobab')
        ->get("/admin/users/{$peer->id}")
        ->assertOk()
        ->assertSee('10.0.0.6')
        ->assertDontSee(__('baobab::admin.users.show.session_revoke_action'));
});

it('revokes a session from the user fiche over HTTP', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-sessions3@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');
    app(GrantPermission::class)($actor, 'baobab.users.impersonate');

    $target = User::create(['name' => 'Target', 'email' => 'target-sessions5@example.com', 'password' => 'secret']);
    insertTestSession('sess-http-revoke', $target->id, now()->timestamp);

    $this->actingAs($actor, 'baobab')
        ->delete("/admin/users/{$target->id}/sessions/sess-http-revoke")
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'sess-http-revoke')->exists())->toBeFalse();
});

it('denies revoking a session without baobab.users.impersonate', function () {
    $actor = User::create(['name' => 'Actor', 'email' => 'actor-sessions4@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($actor, 'baobab.admin.access');

    $target = User::create(['name' => 'Target', 'email' => 'target-sessions6@example.com', 'password' => 'secret']);
    insertTestSession('sess-http-denied', $target->id, now()->timestamp);

    $this->actingAs($actor, 'baobab')
        ->delete("/admin/users/{$target->id}/sessions/sess-http-denied")
        ->assertForbidden();

    expect(DB::table('sessions')->where('id', 'sess-http-denied')->exists())->toBeTrue();
});
