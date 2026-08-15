<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * `module:sync`, l'appelant terminal de `SyncModuleManifest` (suivi n° 111,
 * Pass B). Adaptateur mince — on vérifie qu'il déclenche l'Action et rapporte ce
 * qu'elle répond, pas qu'il rejoue sa logique, couverte par
 * `SyncModuleManifestTest`.
 *
 * **L'écran Modules n'appelle plus cette Action** depuis le n° 156 : il déclenche
 * `UpdateModule`, qui la compose après les migrations en attente. Ses cas vivent
 * donc dans `UpdateModuleTest`. La resynchronisation seule reste une étape de la
 * spec §3 à part entière, mais seul le terminal l'offre — c'est un sous-ensemble
 * strict de la mise à jour, et l'écran n'a pas à faire choisir.
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
