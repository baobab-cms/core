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
function fleetApiActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Fleet API Actor {$counter}",
        'email' => "fleet-api-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * Génère, installe, active un blueprint garage-api/fleet avec les routes API
 * activées (`entity.routes.api`, opt-in) puis rejoue réellement
 * `BaobabServiceProvider::bootstrapActiveModules()` — même technique que
 * `AdminCrudGeneratorTest`, voir son docblock. Vendor distinct de
 * `AdminCrudGeneratorTest`/`FrontCrudGeneratorTest` (slug `fleet` commun,
 * routes/permissions dérivées du slug seul donc inchangées) : ces trois
 * suites installent+activent réellement leur module (`require` les classes
 * générées, contrairement à `ModuleGeneratorTest`, qui ne fait qu'inspecter
 * le disque) — un vendor partagé ferait collisionner leurs namespaces PHP
 * (`Garage\Fleet\Models\Car`) au sein du même process Pest, PHP refusant de
 * redéfinir une classe déjà chargée par un fichier généré avec des champs
 * différents (bug réel rencontré : `status` silencieusement absent du
 * `$fillable` d'un modèle chargé par un autre fichier de test en premier).
 */
function generateInstallAndBootFleetApiModule(): void
{
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-api/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'routes' => ['admin' => false, 'api' => true],
            'fields' => [
                ['key' => 'brand', 'type' => 'text', 'required' => true],
            ],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    app(InstallModule::class)('garage-api/fleet');
    app(ActivateModule::class)('garage-api/fleet');

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

it('lists, creates, updates and deletes entries through the generated REST API', function () {
    generateInstallAndBootFleetApiModule();
    $actor = fleetApiActor(['fleet.cars.view', 'fleet.cars.create', 'fleet.cars.update', 'fleet.cars.delete']);
    $this->actingAs($actor, 'baobab');

    $this->getJson('/api/v1/fleet/cars')->assertOk()->assertJsonStructure(['data']);

    $created = $this->postJson('/api/v1/fleet/cars', ['brand' => 'Renault'])
        ->assertCreated()
        ->assertJsonPath('data.brand', 'Renault');

    $id = $created->json('data.id');

    $this->getJson("/api/v1/fleet/cars/{$id}")->assertOk()->assertJsonPath('data.brand', 'Renault');

    $this->putJson("/api/v1/fleet/cars/{$id}", ['brand' => 'Peugeot'])
        ->assertOk()
        ->assertJsonPath('data.brand', 'Peugeot');

    expect(DB::table('cars')->first()->brand)->toBe('Peugeot');

    $this->deleteJson("/api/v1/fleet/cars/{$id}")->assertNoContent();

    expect(DB::table('cars')->count())->toBe(0);
});

it('rejects a request without the entity permission', function () {
    generateInstallAndBootFleetApiModule();
    $actor = fleetApiActor([]);
    $this->actingAs($actor, 'baobab');

    $this->getJson('/api/v1/fleet/cars')->assertForbidden();
});

it('rejects an unauthenticated request', function () {
    generateInstallAndBootFleetApiModule();

    $this->getJson('/api/v1/fleet/cars')->assertUnauthorized();
});
