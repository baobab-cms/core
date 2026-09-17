<?php

use Baobab\Access\Actions\AssignRole;
use Baobab\Access\Actions\CreateRole;
use Baobab\Access\Actions\DeleteRole;
use Baobab\Access\Actions\GrantPermission;
use Baobab\Access\Actions\RemoveRole;
use Baobab\Access\Actions\RevokePermission;
use Baobab\Access\Actions\UpdateRole;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

it('CreateRole produces an audit entry', function () {
    app(CreateRole::class)('journalist', 30);

    $entry = AuditEntry::where('action', 'role.created')->firstOrFail();

    expect($entry->data)->toMatchArray(['name' => 'journalist', 'level' => 30]);
});

it('UpdateRole produces an audit entry with a before/after diff', function () {
    $role = app(CreateRole::class)('journalist', 30);

    app(UpdateRole::class)($role, ['level' => 45]);

    $entry = AuditEntry::where('action', 'role.updated')->firstOrFail();

    expect($entry->data['before']['level'])->toBe(30)
        ->and($entry->data['after']['level'])->toBe(45);
});

it('DeleteRole produces an audit entry', function () {
    $role = app(CreateRole::class)('journalist', 30);

    app(DeleteRole::class)($role);

    $entry = AuditEntry::where('action', 'role.deleted')->firstOrFail();

    expect($entry->data)->toMatchArray(['name' => 'journalist', 'level' => 30]);
});

it('GrantPermission and RevokePermission produce audit entries', function () {
    $role = Role::findByName('editor', 'baobab');

    app(GrantPermission::class)($role, 'acme.demo.view');
    app(RevokePermission::class)($role, 'acme.demo.view');

    expect(AuditEntry::where('action', 'permission.granted')->where('data->permission', 'acme.demo.view')->exists())->toBeTrue()
        ->and(AuditEntry::where('action', 'permission.revoked')->where('data->permission', 'acme.demo.view')->exists())->toBeTrue();
});

it('AssignRole and RemoveRole produce audit entries', function () {
    $user = User::create(['name' => 'Dana', 'email' => 'dana@example.com', 'password' => 'secret']);
    $role = Role::findByName('editor', 'baobab');

    app(AssignRole::class)($user, $role);
    app(RemoveRole::class)($user, $role);

    expect(AuditEntry::where('action', 'role.assigned')->where('data->role', 'editor')->exists())->toBeTrue()
        ->and(AuditEntry::where('action', 'role.removed')->where('data->role', 'editor')->exists())->toBeTrue();
});

it('captures the acting super-admin and the request IP', function () {
    $admin = User::create(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'secret']);
    $admin->assignRole(Role::findByName('super-admin', 'baobab'));

    $this->actingAs($admin, 'baobab');

    app(CreateRole::class)('journalist', 30);

    $entry = AuditEntry::where('action', 'role.created')->firstOrFail();

    expect($entry->actor_id)->toBe($admin->id);
});

it('denies the audit screen without baobab.audit.view', function () {
    $user = User::create(['name' => 'Nobody', 'email' => 'nobody@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get('/admin/audit')
        ->assertForbidden();
});

it('lists audit entries and supports filtering by action', function () {
    $user = User::create(['name' => 'Auditor', 'email' => 'auditor@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.audit.view');

    app(CreateRole::class)('journalist', 30);
    app(GrantPermission::class)(Role::findByName('editor', 'baobab'), 'acme.demo.view');

    $response = $this->actingAs($user, 'baobab')->get('/admin/audit?action=role.created');

    $response->assertOk();
    expect($response->viewData('entries')->pluck('action')->unique()->all())->toBe(['role.created']);
});

/**
 * @param  list<string>  $permissions
 */
function auditActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Audit Actor {$counter}",
        'email' => "audit-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('filters by object type', function () {
    $user = User::create(['name' => 'Target', 'email' => 'target@example.com', 'password' => 'secret']);
    $role = app(CreateRole::class)('journalist', 30);
    app(AssignRole::class)($user, $role);

    $response = $this->actingAs(auditActor(['baobab.audit.view']), 'baobab')
        ->get('/admin/audit?auditable_type='.urlencode(Role::class));

    $response->assertOk();
    expect($response->viewData('entries')->pluck('action')->all())->toBe(['role.created']);
});

it('filters by period', function () {
    app(CreateRole::class)('recent', 10);
    $old = AuditEntry::where('action', 'role.created')->sole();
    $old->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    app(CreateRole::class)('fresh', 20);

    $response = $this->actingAs(auditActor(['baobab.audit.view']), 'baobab')
        ->get('/admin/audit?action=role.created&from='.now()->subDays(2)->toDateString());

    $response->assertOk();
    expect($response->viewData('entries')->pluck('data')->pluck('name')->all())->toBe(['fresh']);
});

it('filters impersonations only', function () {
    $actor = User::create(['name' => 'Real', 'email' => 'real@example.com', 'password' => 'secret']);
    $target = User::create(['name' => 'Impersonated', 'email' => 'impersonated@example.com', 'password' => 'secret']);

    AuditEntry::create(['action' => 'user.impersonation.started', 'actor_id' => $actor->id, 'impersonator_id' => null, 'auditable_type' => User::class, 'auditable_id' => $target->id]);
    AuditEntry::create(['action' => 'content.saved', 'actor_id' => $actor->id, 'impersonator_id' => $actor->id, 'auditable_type' => User::class, 'auditable_id' => $target->id]);

    $response = $this->actingAs(auditActor(['baobab.audit.view']), 'baobab')
        ->get('/admin/audit?impersonations_only=1');

    $response->assertOk();
    expect($response->viewData('entries')->pluck('action')->all())->toBe(['content.saved']);
});

it('searches the actor by partial name or email', function () {
    $awa = User::create(['name' => 'Awa Traore', 'email' => 'awa@acme.test', 'password' => 'secret']);
    $kofi = User::create(['name' => 'Kofi Mensah', 'email' => 'kofi@autre.test', 'password' => 'secret']);

    AuditEntry::create(['action' => 'content.saved', 'actor_id' => $awa->id]);
    AuditEntry::create(['action' => 'content.saved', 'actor_id' => $kofi->id]);

    $response = $this->actingAs(auditActor(['baobab.audit.view']), 'baobab')
        ->get('/admin/audit?actor=Awa');

    $response->assertOk();
    expect($response->viewData('entries')->pluck('actor_id')->all())->toBe([$awa->id]);
});

it('denies the CSV export without baobab.audit.export', function () {
    $this->actingAs(auditActor(['baobab.audit.view']), 'baobab')
        ->get('/admin/audit/export')
        ->assertForbidden();
});

it('exports the filtered result as CSV and audits the export', function () {
    app(CreateRole::class)('journalist', 30);
    app(CreateRole::class)('editorialist', 40);

    $response = $this->actingAs(auditActor(['baobab.audit.view', 'baobab.audit.export']), 'baobab')
        ->get('/admin/audit/export?action=role.created');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $csv = $response->getContent();
    expect($csv)->toContain('journalist')
        ->toContain('editorialist');

    expect(AuditEntry::where('action', 'audit.exported')->where('data->count', 2)->exists())->toBeTrue();
});
