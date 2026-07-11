<?php

use Baobab\Access\Actions\GrantPermission;
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

/**
 * A user assigned to the given role, granted baobab.admin.access and
 * baobab.users.impersonate directly. Used as the acting party in
 * ImpersonationTest.php.
 */
function impersonationActor(string $roleName): User
{
    $user = User::create([
        'name' => "Actor ({$roleName})",
        'email' => strtolower($roleName).'-actor-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName($roleName, 'baobab'));
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.users.impersonate');

    return $user;
}

/**
 * A user assigned to the given role, with no permissions granted — the
 * impersonation target in ImpersonationTest.php.
 */
function impersonationTarget(string $roleName): User
{
    $user = User::create([
        'name' => "Target ({$roleName})",
        'email' => strtolower($roleName).'-target-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $user->assignRole(Role::findByName($roleName, 'baobab'));

    return $user;
}

/**
 * Path of today's baobab technical log file (daily channel, spec 12 §9).
 * Used by LoggerTest.php.
 */
function baobabLogPath(): string
{
    return storage_path('logs/baobab-'.now()->format('Y-m-d').'.log');
}

/**
 * A minimal valid Content Type blueprint (JSON), key "Car" by default.
 * Used by ContentTypeBlueprintTest.php and CreateContentTypeTest.php.
 *
 * @param  array<string, mixed>  $overrides
 */
function carBlueprintJson(array $overrides = []): string
{
    return (string) json_encode(array_replace([
        'key' => 'Car',
        'label' => ['singular' => 'Voiture', 'plural' => 'Voitures'],
    ], $overrides));
}

/**
 * Répertoire temporaire cible du générateur de Content Types dans les tests
 * (config baobab.content_types.modules_path). Utilisé par
 * ContentTypeModuleGeneratorTest.php et BuildContentTypeTest.php.
 */
function generatedModulesPath(): string
{
    return sys_get_temp_dir().'/baobab-test-content-type-modules';
}
