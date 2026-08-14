<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Les deux autres appelants de `SyncModuleManifest` (suivi n° 111, Pass B) :
 * la CLI, qui sert le module mis à jour par Composer, et l'écran Modules, qui
 * sert la persona non-technicienne. Adaptateurs minces — on vérifie qu'ils
 * déclenchent l'Action et rapportent ce qu'elle répond, pas qu'ils rejouent sa
 * logique, couverte par `SyncModuleManifestTest`.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function syncAdapterActor(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Sync Actor {$counter}",
        'email' => "sync-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.modules.manage');

    return $user;
}

it('syncs from the command line and reports what changed', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
        ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
        ['key' => 'synced.car.delete', 'label' => 'Supprimer les voitures'],
    ]]));

    $this->artisan('module:sync', ['name' => $module->name])
        ->expectsOutputToContain('synced.car.delete')
        ->assertSuccessful();

    expect(ModulePermission::where('module_id', $module->id)->count())->toBe(3);
});

it('refuses a removal from the command line without --force', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    $this->artisan('module:sync', ['name' => $module->name])->assertFailed();

    // Rien n'a bougé : la commande échoue au lieu de retirer en silence.
    expect(ModulePermission::where('module_id', $module->id)->count())->toBe(2);
});

it('applies the removal from the command line with --force', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    $this->artisan('module:sync', ['name' => $module->name, '--force' => true])->assertSuccessful();

    expect(ModulePermission::where('module_id', $module->id)->count())->toBe(1);
});

it('says nothing changed when nothing changed', function () {
    $module = syncInstalledModule();

    $this->artisan('module:sync', ['name' => $module->name])
        ->expectsOutputToContain('already up to date')
        ->assertSuccessful();
});

it('fails on an unknown module name', function () {
    $this->artisan('module:sync', ['name' => 'garage/nope'])->assertFailed();
});

it('syncs from the Modules screen', function () {
    $module = syncInstalledModule();
    $actor = syncAdapterActor();

    syncWriteManifest(syncManifestArray(['title' => 'Synced Fleet']));

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.sync', ['vendor' => $vendor, 'slug' => $slug]))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast');

    expect($module->fresh()?->title)->toBe('Synced Fleet');
});

it('shows the refusal message on the Modules screen instead of removing', function () {
    $module = syncInstalledModule();
    $actor = syncAdapterActor();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.sync', ['vendor' => $vendor, 'slug' => $slug]))
        ->assertRedirect(route('admin.modules.index'));

    // Le message de l'Action est déjà écrit pour un humain : l'écran l'affiche
    // tel quel, et la permission survit.
    expect(session('toast')['type'])->toBe('error')
        ->and(session('toast')['message'])->toContain('synced.car.update')
        ->and(ModulePermission::where('module_id', $module->id)->count())->toBe(2);
});

it('applies the removal from the Modules screen when the box is checked', function () {
    $module = syncInstalledModule();
    $actor = syncAdapterActor();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.sync', ['vendor' => $vendor, 'slug' => $slug]), ['force' => '1'])
        ->assertRedirect(route('admin.modules.index'));

    expect(ModulePermission::where('module_id', $module->id)->count())->toBe(1);
});

it('guards the sync route behind the modules permission', function () {
    $module = syncInstalledModule();

    $user = User::create(['name' => 'Sans droits', 'email' => 'sans-droits-sync@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.modules.sync', ['vendor' => $vendor, 'slug' => $slug]))
        ->assertForbidden();
});
