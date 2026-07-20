<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Api\Models\ApiSetting;
use Baobab\Users\Models\User;

it('serves /api/docs by default outside of production', function () {
    test()->get('/api/docs')->assertOk()->assertSee('api-reference', false);
});

it('responds 404 to /api/docs once docs_enabled is turned off', function () {
    ApiSetting::current()->fill(['docs_enabled' => false])->save();

    test()->get('/api/docs')->assertStatus(404);
});

it('requires baobab.system.api.manage to view /api/docs in production', function () {
    // Un admin a explicitement réactivé la doc en production — sinon
    // docs_enabled est déjà à false par défaut dans cet environnement,
    // masquant le test de la permission derrière un 404 différent.
    ApiSetting::current()->fill(['docs_enabled' => true])->save();
    app()->detectEnvironment(fn () => 'production');

    test()->get('/api/docs')->assertStatus(403);

    $user = User::create(['name' => 'API admin', 'email' => 'docs-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.api.manage');

    test()->actingAs($user, 'baobab')->get('/api/docs')->assertOk();
});

it('shows and persists the docs_enabled toggle on the API settings screen', function () {
    $user = User::create(['name' => 'API admin', 'email' => 'settings-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.api.manage');

    test()->actingAs($user, 'baobab')
        ->post(route('admin.api.update'), [
            'rest_enabled' => '1',
            'rate_limit_per_minute' => 60,
            'docs_enabled' => '0',
        ])
        ->assertRedirect(route('admin.api.index'));

    expect(ApiSetting::current()->docs_enabled)->toBeFalse();
});
