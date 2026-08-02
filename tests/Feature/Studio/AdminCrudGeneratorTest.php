<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\BaobabServiceProvider;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * @param  list<string>  $permissions
 */
function fleetActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Fleet Actor {$counter}",
        'email' => "fleet-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * Génère, installe, active un blueprint garage/fleet (une seule entité Car,
 * champs brand/text requis + status/select) puis rejoue réellement
 * `BaobabServiceProvider::bootstrapActiveModules()` (méthode privée, invoquée
 * par réflexion) — exactement ce qu'un vrai prochain boot ferait maintenant
 * que le module est actif en base, `ActivateModule` ne chargeant jamais le
 * provider dans le processus courant (cf. son docblock). Vérifie le code de
 * production réel, pas une simulation manuelle : révèle le bug corrigé dans
 * `bootstrapActiveModules()` (`register()` seul, sans `boot()` explicite, ne
 * bootait jamais le provider d'un module — invisible tant qu'aucun provider
 * de module n'avait de logique dans `boot()`, cf. suivi n° 91).
 */
function generateInstallAndBootFleetModule(array $blueprintOverrides = []): void
{
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson(array_replace([
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'fields' => [
                ['key' => 'brand', 'type' => 'text', 'required' => true],
                ['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published']]],
            ],
        ]],
    ], $blueprintOverrides)));

    app(ModuleGenerator::class)($blueprint);

    app(InstallModule::class)('garage/fleet');
    app(ActivateModule::class)('garage/fleet');

    $provider = app()->getProvider(BaobabServiceProvider::class);
    (new ReflectionMethod($provider, 'bootstrapActiveModules'))->invoke($provider);
}

beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

it('denies the generated admin screens without the entity permission', function () {
    generateInstallAndBootFleetModule();
    $actor = fleetActor([]);

    $this->actingAs($actor, 'baobab')
        ->get(route('admin.fleet.cars.index'))
        ->assertForbidden();
});

it('lists, creates, updates and deletes entries through the generated admin CRUD', function () {
    generateInstallAndBootFleetModule();
    $actor = fleetActor(['fleet.cars.view', 'fleet.cars.create', 'fleet.cars.update', 'fleet.cars.delete']);
    $this->actingAs($actor, 'baobab');

    $this->get(route('admin.fleet.cars.index'))->assertOk();

    // Régression : le formulaire généré rendait `<x-dynamic-component>` tel
    // quel, sans compiler les champs, quand le `@if`/`@else` choisissant
    // `:checked` vs `:value` vivait À L'INTÉRIEUR de la balise du composant
    // — le compilateur de tags Blade ne tolère aucune directive dans la
    // liste d'attributs. `assertOk()` seul ne l'aurait jamais détecté.
    $this->get(route('admin.fleet.cars.create'))
        ->assertOk()
        ->assertSee('name="brand"', false)
        ->assertSee('name="status"', false)
        ->assertDontSee('x-dynamic-component', false);

    $this->post(route('admin.fleet.cars.store'), [
        'brand' => 'Renault',
        'status' => 'draft',
    ])->assertRedirect(route('admin.fleet.cars.index'));

    expect(DB::table('cars')->count())->toBe(1);
    $car = DB::table('cars')->first();
    expect($car->brand)->toBe('Renault')
        ->and($car->status)->toBe('draft');

    $this->get(route('admin.fleet.cars.edit', $car->id))
        ->assertOk()
        ->assertSee('name="brand"', false)
        ->assertSee('value="Renault"', false);

    $this->put(route('admin.fleet.cars.update', $car->id), [
        'brand' => 'Peugeot',
        'status' => 'published',
    ])->assertRedirect(route('admin.fleet.cars.index'));

    expect(DB::table('cars')->first()->brand)->toBe('Peugeot');

    $this->delete(route('admin.fleet.cars.destroy', $car->id))
        ->assertRedirect(route('admin.fleet.cars.index'));

    expect(DB::table('cars')->count())->toBe(0);
});

it('rejects an invalid submission with a validation error, without creating a row', function () {
    generateInstallAndBootFleetModule();
    $actor = fleetActor(['fleet.cars.view', 'fleet.cars.create']);
    $this->actingAs($actor, 'baobab');

    $this->post(route('admin.fleet.cars.store'), [
        'status' => 'draft',
    ])->assertSessionHasErrors('brand');

    expect(DB::table('cars')->count())->toBe(0);
});
