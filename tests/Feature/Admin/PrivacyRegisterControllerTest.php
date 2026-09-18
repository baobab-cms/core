<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

/**
 * @param  list<string>  $permissions
 */
function privacyRegisterActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Privacy Actor {$counter}",
        'email' => "privacy-register-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

it('denies the register without baobab.privacy.register.view', function () {
    $this->actingAs(privacyRegisterActor([]), 'baobab')
        ->get(route('admin.privacy.register.index'))
        ->assertForbidden();

    $this->actingAs(privacyRegisterActor([]), 'baobab')
        ->get(route('admin.privacy.register.export'))
        ->assertForbidden();
});

it('shows every core declaration on the register screen', function () {
    $this->actingAs(privacyRegisterActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.privacy.register.index'))
        ->assertOk()
        ->assertSee(__('baobab::privacy.users.title'))
        ->assertSee(__('baobab::privacy.audit_log.title'))
        ->assertSee(__('baobab::privacy.form_submissions.title'))
        ->assertSee('core.mail_log');
});

it('downloads the register as a standalone HTML document', function () {
    $response = $this->actingAs(privacyRegisterActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.privacy.register.export'))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/html')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->getContent())->toContain('<!DOCTYPE html>')
        ->and($response->getContent())->toContain(__('baobab::privacy.users.title'));
});

it('links the register from the sidebar only with the permission', function () {
    $this->actingAs(privacyRegisterActor(['baobab.privacy.register.view']), 'baobab')
        ->get(route('admin.privacy.register.index'))
        ->assertSee(route('admin.privacy.register.index'), false);

    $this->actingAs(privacyRegisterActor([]), 'baobab')
        ->get(route('admin.dashboard'))
        ->assertDontSee(route('admin.privacy.register.index'), false);
});

it('seeds the permission for the admin role only', function () {
    $admin = Role::where('name', 'admin')->where('guard_name', 'baobab')->first();
    $editor = Role::where('name', 'editor')->where('guard_name', 'baobab')->first();

    expect($admin?->hasPermissionTo('baobab.privacy.register.view'))->toBeTrue()
        ->and($editor?->hasPermissionTo('baobab.privacy.register.view'))->toBeFalse();
});
