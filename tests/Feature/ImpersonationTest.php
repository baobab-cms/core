<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\AuditLogger;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Facades\Hook;

it('refuses to impersonate a user of level equal to or above the actor\'s', function () {
    $actor = impersonationActor('admin');
    $peer = impersonationTarget('admin');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$peer->id}/impersonate")
        ->assertStatus(500);

    $this->assertAuthenticatedAs($actor, 'baobab');
});

it('refuses to impersonate another super-admin', function () {
    $actor = impersonationActor('super-admin');
    $otherSuperAdmin = impersonationTarget('super-admin');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$otherSuperAdmin->id}/impersonate")
        ->assertStatus(500);
});

it('starts an impersonation, switching the authenticated identity', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate")
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($target, 'baobab');
    expect(session('baobab.impersonator_id'))->toBe($actor->id)
        ->and(session('baobab.impersonation_expires_at'))->not->toBeNull();
});

it('refuses to nest a second impersonation', function () {
    $actor = impersonationActor('admin');
    // Grant the target the same permissions as an actor, so the request
    // reaches the no-nesting guard itself rather than being turned away
    // earlier by the can:baobab.users.impersonate route gate.
    $target = impersonationActor('editor');
    $secondTarget = impersonationTarget('moderator');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->post("/admin/users/{$secondTarget->id}/impersonate")
        ->assertStatus(500);
});

it('stops an impersonation and restores the real actor', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->post('/impersonation/stop')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($actor, 'baobab');
    expect(session('baobab.impersonator_id'))->toBeNull();
});

it('keeps the stop route reachable even when the target lacks baobab.admin.access', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor'); // no baobab.admin.access granted

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->post('/impersonation/stop')->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($actor, 'baobab');
});

it('automatically restores the real actor once the impersonation has expired', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    session()->put('baobab.impersonation_expires_at', now()->subMinute()->toIso8601String());

    $this->get('/admin')->assertOk();

    $this->assertAuthenticatedAs($actor, 'baobab');
    expect(session('baobab.impersonator_id'))->toBeNull();
});

it('blocks the access matrix while impersonating', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');
    app(GrantPermission::class)($target, 'baobab.access.manage');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->get('/admin/access')->assertForbidden();
});

it('blocks the account security screen while impersonating', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->get('/admin/account/security')->assertForbidden();
    $this->post('/admin/account/security/disable', ['current_password' => 'secret'])->assertForbidden();
});

it('blocks the privacy register and requests while impersonating (spec 16 §7)', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');
    app(GrantPermission::class)($target, 'baobab.privacy.register.view');
    app(GrantPermission::class)($target, 'baobab.privacy.requests.manage');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->get(route('admin.privacy.register.index'))->assertForbidden();
    $this->get(route('admin.privacy.requests.index'))->assertForbidden();
    $this->post(route('admin.privacy.requests.store'), ['subject' => 'someone@example.com'])->assertForbidden();
});

/**
 * Audit sécurité du 7 septembre 2026 (constat n° 3) : sans ce blocage,
 * l'identité impersonée pouvait créer un token API Sanctum, arrêter
 * l'impersonation, et conserver un accès durable hors de toute session
 * d'impersonation.
 */
it('blocks creating and revoking API tokens while impersonating', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');
    app(GrantPermission::class)($target, 'baobab.admin.access');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->get('/admin/account/api-tokens')->assertForbidden();
    $this->post('/admin/account/api-tokens', ['name' => 'x', 'abilities' => ['baobab.admin.access']])->assertForbidden();
    $this->delete('/admin/account/api-tokens/1')->assertForbidden();
});

it('records a user.impersonation.started audit entry naming the real actor', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $entry = AuditEntry::where('action', 'user.impersonation.started')->where('auditable_id', $target->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($actor->id)
        ->and($entry->impersonator_id)->toBeNull();
});

it('captures the double identity on audit entries recorded while impersonating', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $entry = app(AuditLogger::class)->record('test.action', null, []);

    expect($entry->actor_id)->toBe($target->id)
        ->and($entry->impersonator_id)->toBe($actor->id);
});

it('records a user.impersonation.ended audit entry naming the real actor once stopped', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");

    $this->post('/impersonation/stop');

    $entry = AuditEntry::where('action', 'user.impersonation.ended')->where('auditable_id', $target->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($actor->id)
        ->and($entry->impersonator_id)->toBeNull();
});

it('emits started/ended hooks', function () {
    $started = false;
    $ended = false;
    Hook::listen('baobab.user.impersonation.started', function () use (&$started): void {
        $started = true;
    });
    Hook::listen('baobab.user.impersonation.ended', function () use (&$ended): void {
        $ended = true;
    });

    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate");
    $this->post('/impersonation/stop');

    expect($started)->toBeTrue()
        ->and($ended)->toBeTrue();
});
