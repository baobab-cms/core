<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

it('denies the user fiche without baobab.users.impersonate', function () {
    $user = User::create(['name' => 'Nobody', 'email' => 'nobody-userfiche@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $target = User::create(['name' => 'Target', 'email' => 'target-userfiche@example.com', 'password' => 'secret']);

    $this->actingAs($user, 'baobab')
        ->get("/admin/users/{$target->id}")
        ->assertForbidden();
});

it('renders the user fiche with roles and activity, and shows the impersonate button', function () {
    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate")
        ->assertRedirect();

    // L'impersonation authentifie désormais la session en tant que $target
    // (double identité, spec 04 §9.1) — pas besoin (et il ne faut pas) de
    // ré-appeler actingAs($actor), ce qui écraserait cet état de session.
    $this->post('/impersonation/stop')
        ->assertRedirect();

    $this->actingAs($actor, 'baobab')
        ->get("/admin/users/{$target->id}")
        ->assertOk()
        ->assertSee($target->name)
        ->assertSee('editor')
        ->assertSee('user.impersonation.started')
        ->assertSee('user.impersonation.ended')
        ->assertSee(__('baobab::admin.users.impersonate_action'));
});

it('hides the impersonate button on a user of equal or higher level', function () {
    $actor = impersonationActor('editor');
    $peer = User::create(['name' => 'Peer', 'email' => 'peer-userfiche@example.com', 'password' => 'secret']);
    $peer->assignRole(Role::findByName('editor', 'baobab'));

    $this->actingAs($actor, 'baobab')
        ->get("/admin/users/{$peer->id}")
        ->assertOk()
        ->assertDontSee(__('baobab::admin.users.impersonate_action'));
});
