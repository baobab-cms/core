<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\UpdateModule;
use Baobab\Hooks\HookRegistry;
use Baobab\Modules\Exceptions\PermissionRemovalNotConfirmedException;
use Baobab\Modules\Models\Module;
use Baobab\Modules\Models\ModulePermission;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi n° 156 — mettre à jour un module dont le code a changé sur disque.
 * Jusqu'ici, trois endroits seulement exécutaient `migrate` dans tout le Core, et
 * aucun ne couvrait ce cas : un module mis à jour par Composer voyait son
 * manifeste rafraîchi mais sa table figée dans l'état de la version précédente.
 *
 * Les fixtures (`syncInstalledModule`, `syncWriteManifest`…) sont celles de
 * `SyncModuleManifestTest` : c'est le même module de test, à une migration près.
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

function updateActor(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Update Actor {$counter}",
        'email' => "update-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.system.modules.manage');

    return $user;
}

/**
 * Dépose sur disque une migration que le module n'a jamais jouée — ce qu'un
 * `composer update` vers une version plus récente produit.
 */
function updateWritePendingMigration(string $table): void
{
    File::ensureDirectoryExists(syncModuleDir().'/database/migrations');
    File::put(syncModuleDir()."/database/migrations/2026_08_14_100000_create_{$table}_table.php", <<<PHP
    <?php

    declare(strict_types=1);

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('{$table}', function (Blueprint \$table): void {
                \$table->id();
                \$table->string('label');
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('{$table}');
        }
    };
    PHP);
}

it('runs the migration a module gained after installation', function () {
    $module = syncInstalledModule();
    $table = 'upd_parts'.syncSuffix();

    expect(Schema::hasTable($table))->toBeFalse();

    updateWritePendingMigration($table);

    $result = app(UpdateModule::class)($module);

    // Le défaut du n° 156 : la migration était sur disque, jamais jouée, et rien
    // ne le signalait.
    expect(Schema::hasTable($table))->toBeTrue()
        ->and($result['migrations'])->toHaveCount(1)
        ->and($result['migrations'][0])->toContain("create_{$table}_table");
});

it('refreshes the manifest in the same pass', function () {
    $module = syncInstalledModule();
    $table = 'upd_parts'.syncSuffix();

    updateWritePendingMigration($table);
    syncWriteManifest(syncManifestArray([
        'version' => '2.0.0',
        'permissions' => [
            ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
            ['key' => 'synced.car.update', 'label' => 'Modifier les voitures'],
            ['key' => 'synced.part.view', 'label' => 'Voir les pièces'],
        ],
    ]));

    $result = app(UpdateModule::class)($module);

    // Les deux moitiés d'une vraie mise à jour : le schéma et ce que le Core
    // sait du module.
    expect(Schema::hasTable($table))->toBeTrue()
        ->and($module->fresh()?->version)->toBe('2.0.0')
        ->and($result['manifest']['permissions']['added'])->toBe(['synced.part.view']);
});

it('is idempotent — a second pass finds nothing left to do', function () {
    $module = syncInstalledModule();
    $table = 'upd_parts'.syncSuffix();

    updateWritePendingMigration($table);

    app(UpdateModule::class)($module);
    $again = app(UpdateModule::class)($module);

    expect($again['migrations'])->toBe([])
        ->and($again['manifest']['manifest_changed'])->toBeFalse();
});

it('updates a module that ships no migration at all', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['title' => 'Synced Fleet']));

    $result = app(UpdateModule::class)($module);

    // Le comportement de l'ancien bouton « Resynchroniser » est intact : un
    // module sans migration en attente n'en souffre pas.
    expect($result['migrations'])->toBe([])
        ->and($module->fresh()?->title)->toBe('Synced Fleet');
});

it('emits the lifecycle hook of the update stage', function () {
    $module = syncInstalledModule();
    $table = 'upd_parts'.syncSuffix();

    $seen = null;
    app(HookRegistry::class)->listen('baobab.module.updated', function (Module $updated, array $result) use (&$seen): void {
        $seen = ['name' => $updated->name, 'migrations' => count($result['migrations'])];
    });

    updateWritePendingMigration($table);

    app(UpdateModule::class)($module);

    expect($seen)->not->toBeNull()
        ->and($seen['name'])->toBe($module->name)
        ->and($seen['migrations'])->toBe(1);
});

it('keeps the guard of the resynchronisation it composes', function () {
    $module = syncInstalledModule();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    app(UpdateModule::class)($module);
})->throws(PermissionRemovalNotConfirmedException::class, 'synced.car.update');

it('updates from the command line and names the migrations it ran', function () {
    $module = syncInstalledModule();
    $table = 'upd_parts'.syncSuffix();

    updateWritePendingMigration($table);

    $this->artisan('module:update', ['name' => $module->name])
        ->expectsOutputToContain("create_{$table}_table")
        ->assertSuccessful();

    expect(Schema::hasTable($table))->toBeTrue();
});

it('says nothing changed from the command line when nothing changed', function () {
    $module = syncInstalledModule();

    $this->artisan('module:update', ['name' => $module->name])
        ->expectsOutputToContain('already up to date')
        ->assertSuccessful();
});

it('fails on the command line for an unknown module', function () {
    $this->artisan('module:update', ['name' => 'garage/nope'])->assertFailed();
});

it('updates from the Modules screen', function () {
    $module = syncInstalledModule();
    $table = 'upd_parts'.syncSuffix();
    $actor = updateActor();

    updateWritePendingMigration($table);

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.update', ['vendor' => $vendor, 'slug' => $slug]))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('toast');

    expect(Schema::hasTable($table))->toBeTrue();
});

it('shows the refusal message on the Modules screen instead of removing', function () {
    $module = syncInstalledModule();
    $actor = updateActor();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.update', ['vendor' => $vendor, 'slug' => $slug]))
        ->assertRedirect(route('admin.modules.index'));

    expect(session('toast')['type'])->toBe('error')
        ->and(session('toast')['message'])->toContain('synced.car.update')
        ->and(ModulePermission::where('module_id', $module->id)->count())->toBe(2);
});

it('applies the removal from the Modules screen when the box is checked', function () {
    $module = syncInstalledModule();
    $actor = updateActor();

    syncWriteManifest(syncManifestArray(['permissions' => [
        ['key' => 'synced.car.view', 'label' => 'Voir les voitures'],
    ]]));

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($actor, 'baobab')
        ->post(route('admin.modules.update', ['vendor' => $vendor, 'slug' => $slug]), ['force' => '1'])
        ->assertRedirect(route('admin.modules.index'));

    expect(ModulePermission::where('module_id', $module->id)->count())->toBe(1);
});

it('guards the update route behind the modules permission', function () {
    $module = syncInstalledModule();

    $user = User::create(['name' => 'Sans droits', 'email' => 'sans-droits-update@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    [$vendor, $slug] = explode('/', $module->name, 2);

    $this->actingAs($user, 'baobab')
        ->post(route('admin.modules.update', ['vendor' => $vendor, 'slug' => $slug]))
        ->assertForbidden();
});
