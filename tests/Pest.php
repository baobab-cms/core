<?php

use Baobab\Modules\Models\Module;
use Baobab\Tests\TestCase;
use Baobab\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function fixtureModulesPath(string $path = ''): string
{
    return __DIR__.'/Fixtures/modules'.($path !== '' ? '/'.$path : '');
}

function makeActiveModule(string $name = 'acme/manual'): Module
{
    return Module::create([
        'name' => $name,
        'title' => $name,
        'type' => 'module',
        'version' => '1.0.0',
        'provider' => 'Acme\\Manual\\Providers\\ManualServiceProvider',
        'source' => 'local',
        'path' => '/tmp/'.$name,
        'manifest' => [],
        'status' => 'active',
    ]);
}

/**
 * Creates a user assigned to the given role (for its `level`) and logs them
 * in via the `baobab` guard. Used by hierarchy-related Feature tests.
 */
function actingAsLevel(string $roleName): User
{
    $user = User::create([
        'name' => "Actor ({$roleName})",
        'email' => strtolower($roleName).'-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName($roleName, 'baobab'));

    test()->actingAs($user, 'baobab');

    return $user;
}
