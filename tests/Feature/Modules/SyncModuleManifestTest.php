<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Actions\Modules\SyncModuleManifest;
use Baobab\Hooks\HookRegistry;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\PermissionRemovalNotConfirmedException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Models\ModuleMenuItem;
use Baobab\Modules\Models\ModulePermission;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

/**
 * Suivi n° 111, Pass B — le manifeste est capturé à l'installation et n'était
 * jamais relu (n° 95). Ces cas écrivent un vrai `module.json` sur disque, le
 * modifient, et vérifient ce que la resynchronisation en propage.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function syncSuffix(bool $next = false): string
{
    static $counter = 0;

    if ($next) {
        $counter++;
    }

    return (string) $counter;
}

function syncModuleName(): string
{
    return 'garage/synced'.syncSuffix();
}

function syncModuleDir(): string
{
    return generatedModulesPath().'/garage-synced'.syncSuffix();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function syncManifestArray(array $overrides = []): array
{
    return [
        'name' => syncModuleName(),
        'title' => 'Synced',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Garage\\Synced\\Providers\\SyncedServiceProvider',
        'autoload' => ['psr-4' => ['Garage\\Synced\\' => 'src/']],
        'permissions' => [
            ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
            ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
        ],
        'menus' => ['admin' => [
            ['label' => 'Voitures', 'route' => 'admin.dashboard', 'order' => 10],
        ]],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $manifest
 */
function syncWriteManifest(array $manifest): void
{
    File::ensureDirectoryExists(syncModuleDir());
    File::put(
        syncModuleDir().'/module.json',
        (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    );
}

/**
 * @param  array<string, mixed>  $overrides
 */
function syncInstalledModule(array $overrides = [], bool $activate = false): Module
{
    syncSuffix(next: true);
    syncWriteManifest(syncManifestArray($overrides));

    $module = app(InstallModule::class)(syncModuleName());

    if ($activate) {
        $module = app(ActivateModule::class)(syncModuleName());
    }

    return $module;
}

it('propagates a changed title, version and manifest block', function () {
    $module = syncInstalledModule();

    expect($module->version)->toBe('1.0.0')
        ->and($module->manifest['hooks'] ?? null)->toBeNull();

    syncWriteManifest(syncManifestArray([
        'title' => 'Synced Fleet',
        'version' => '2.1.0',
        'hooks' => ['emits' => ['garage.car.serviced']],
    ]));

    $result = app(SyncModuleManifest::class)($module);

    $module->refresh();

    expect($module->title)->toBe('Synced Fleet')
        ->and($module->version)->toBe('2.1.0')
        ->and($module->manifest['hooks']['emits'])->toBe(['garage.car.serviced'])
        ->and($result['manifest_changed'])->toBeTrue();
});

it('adds a permission the manifest gained', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
        ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
        ['key' => 'synced.car.delete', 'label' => 'Supprimer les voitures'],
    ]]));

    $result = app(SyncModuleManifest::class)($module);

    expect($result['permissions']['added'])->toBe(['synced.car.delete'])
        ->and(ModulePermission::where('module_id', $module->id)->pluck('key')->all())
        ->toContain('synced.car.delete');
});

it('creates the Spatie permission of a new key when the module is active', function () {
    $module = syncInstalledModule(activate: true);

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
        ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
        ['key' => 'synced.car.delete', 'label' => 'Supprimer les voitures'],
    ]]));

    app(SyncModuleManifest::class)($module);

    // ActivateModule crée les permissions Spatie à l'activation : une clé
    // déclarée après coup n'y repasserait jamais.
    expect(Permission::where('name', 'synced.car.delete')->where('guard_name', 'baobab')->exists())->toBeTrue();
});

it('updates a label without touching the granted permission', function () {
    $module = syncInstalledModule(activate: true);

    $permission = Permission::findOrCreate('synced.car.view', 'baobab');
    $permissionId = $permission->id;

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Consulter les voitures'],
        ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
    ]]));

    $result = app(SyncModuleManifest::class)($module);

    // Le libellé change, la permission Spatie ne bouge pas : la recréer
    // perdrait les attributions, donc un simple renommage revoquerait des droits.
    expect($result['permissions']['updated'])->toBe(['synced.car.view'])
        ->and(ModulePermission::where('key', 'synced.car.view')->value('label'))->toBe('Consulter les voitures')
        ->and(Permission::where('name', 'synced.car.view')->value('id'))->toBe($permissionId);
});

it('refuses to remove a permission without an explicit confirmation', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    app(SyncModuleManifest::class)($module);
})->throws(PermissionRemovalNotConfirmedException::class, 'synced.car.update');

it('changes nothing at all when the removal is refused', function () {
    $module = syncInstalledModule(['title' => 'Avant']);

    syncWriteManifest(syncManifestArray([
        'title' => 'Après',
        'permissions' => [['key' => 'synced.car.view', 'label' => 'Voir les voitures']],
    ]));

    try {
        app(SyncModuleManifest::class)($module);
    } catch (PermissionRemovalNotConfirmedException) {
        // Attendu.
    }

    // La garde passe avant la première écriture : le titre n'a pas bougé non plus.
    expect($module->fresh()?->title)->toBe('Avant')
        ->and(ModulePermission::where('module_id', $module->id)->count())->toBe(2);
});

it('removes the permission and revokes it once confirmed', function () {
    $module = syncInstalledModule(activate: true);

    Permission::findOrCreate('synced.car.update', 'baobab');

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    $result = app(SyncModuleManifest::class)($module, confirmDestructive: true);

    expect($result['permissions']['removed'])->toBe(['synced.car.update'])
        ->and(ModulePermission::where('key', 'synced.car.update')->exists())->toBeFalse()
        // La permission Spatie part avec : c'est elle qui porte les attributions.
        ->and(Permission::where('name', 'synced.car.update')->where('guard_name', 'baobab')->exists())->toBeFalse();
});

it('replaces the menu entries, nesting included', function () {
    $module = syncInstalledModule();

    expect(ModuleMenuItem::where('module_id', $module->id)->count())->toBe(1);

    syncWriteManifest(syncManifestArray(['menus' => ['admin' => [
        [
            'label' => 'Garage',
            'order' => 5,
            'children' => [
                ['label' => 'Voitures', 'route' => 'admin.dashboard', 'order' => 10],
                ['label' => 'Clients', 'route' => 'admin.dashboard', 'order' => 20],
            ],
        ],
    ]]]));

    $result = app(SyncModuleManifest::class)($module);

    $items = ModuleMenuItem::where('module_id', $module->id)->get();

    expect($result['menu_items'])->toBe(3)
        ->and($items)->toHaveCount(3)
        ->and($items->where('label', 'Voitures')->first()?->parent_id)
        ->toBe($items->where('label', 'Garage')->first()?->id);
});

it('reports an unchanged manifest as unchanged', function () {
    $module = syncInstalledModule();

    $result = app(SyncModuleManifest::class)($module);

    expect($result['manifest_changed'])->toBeFalse()
        ->and($result['permissions'])->toBe(['added' => [], 'updated' => [], 'removed' => []]);
});

it('refuses a module whose files have disappeared from disk', function () {
    $module = syncInstalledModule();

    File::deleteDirectory(syncModuleDir());

    app(SyncModuleManifest::class)($module);
})->throws(ModuleNotFoundException::class);

it('emits the lifecycle hook with what actually changed', function () {
    $module = syncInstalledModule();

    $seen = null;
    app(HookRegistry::class)->listen('baobab.module.manifest_synced', function (Module $synced, array $result) use (&$seen): void {
        $seen = ['name' => $synced->name, 'added' => $result['permissions']['added']];
    });

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
        ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
        ['key' => 'synced.car.delete', 'label' => 'Supprimer les voitures'],
    ]]));

    app(SyncModuleManifest::class)($module);

    // Étape du cycle de vie à part entière depuis l'amendement de la
    // spec-modules §3 (suivi n° 155) : un module tiers doit pouvoir réagir au
    // fait que les permissions ou les menus d'un autre viennent de changer.
    expect($seen)->not->toBeNull()
        ->and($seen['name'])->toBe($module->name)
        ->and($seen['added'])->toBe(['synced.car.delete']);
});

it('does not change the activation state', function () {
    $module = syncInstalledModule(activate: true);

    syncWriteManifest(syncManifestArray(['version' => '3.0.0']));

    app(SyncModuleManifest::class)($module);

    expect($module->fresh()?->status)->toBe('active');
});
