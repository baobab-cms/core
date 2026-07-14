<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Audit\Models\AuditEntry;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Users\Models\User;

/**
 * @param  list<string>  $permissions
 */
function brandingActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Branding Actor {$counter}",
        'email' => "branding-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the branding screen without baobab.system.branding.manage', function () {
    $actor = brandingActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.branding.index'))
        ->assertForbidden();
});

it('shows the branding screen to an actor with baobab.system.branding.manage', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.branding.index'))
        ->assertOk();
});

it('updates the primary color and audits the change', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['primary_color' => '#112233'])
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('toast');

    expect(BrandingSetting::current()->primary_color)->toBe('#112233');
    expect(AuditEntry::where('action', 'branding.updated')->exists())->toBeTrue();
});

it('rejects an invalid color', function () {
    $actor = brandingActor(['baobab.system.branding.manage']);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.branding.update'), ['primary_color' => 'not-a-color'])
        ->assertSessionHasErrors('primary_color');
});
