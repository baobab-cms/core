<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\BaobabServiceProvider;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Génère, installe, active un blueprint garage-front/fleet avec les routes
 * front activées (`entity.routes.front`, opt-in — désactivées par défaut,
 * contrairement à l'admin) puis rejoue réellement
 * `BaobabServiceProvider::bootstrapActiveModules()` — même technique que
 * `AdminCrudGeneratorTest`, voir son docblock. Vendor distinct de
 * `AdminCrudGeneratorTest`/`ApiCrudGeneratorTest` (slug `fleet` commun,
 * routes/permissions dérivées du slug seul donc inchangées) — voir docblock
 * de `generateInstallAndBootFleetApiModule()` dans `ApiCrudGeneratorTest`
 * pour la collision de namespace PHP que ceci évite.
 */
function generateInstallAndBootFleetFrontModule(): void
{
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-front/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'routes' => ['admin' => false, 'front' => true],
            'fields' => [
                ['key' => 'brand', 'type' => 'text', 'required' => true],
            ],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    app(InstallModule::class)('garage-front/fleet');
    app(ActivateModule::class)('garage-front/fleet');

    $provider = app()->getProvider(BaobabServiceProvider::class);
    (new ReflectionMethod($provider, 'bootstrapActiveModules'))->invoke($provider);

    sinkGenericPublicRouteBelowModuleRoutes();
}

/**
 * Artefact propre à ce banc de test, pas à la production : en conditions
 * réelles, `bootstrapActiveModules()` s'exécute toujours avant
 * `registerPublicRoutes()` dans un seul et même `boot()` par requête (cf.
 * commentaire dans `BaobabServiceProvider::boot()`), donc la route générique
 * `{prefix}/{slug?}` (`baobab.public.show`) est structurellement enregistrée
 * après toute route front de module. Ici, `registerPublicRoutes()` s'est déjà
 * exécuté une première fois pendant le boot initial de Testbench, avant même
 * que le module `garage-front/fleet` n'existe — le rejeu par réflexion de
 * `bootstrapActiveModules()` ci-dessus ajoute donc `fleet/cars` **après**
 * cette route générique déjà enregistrée, ce qui l'intercepterait en premier
 * (2 segments, aucun `where()` ne l'exclut, cf. `PublicRouteRegistrar`).
 * Repousse `baobab.public.show` en fin de la liste des routes GET du routeur
 * pour reproduire l'ordre réel, sans dupliquer/reconstruire toute
 * l'application (`refreshApplication()` perdrait la BDD SQLite `:memory:`).
 */
function sinkGenericPublicRouteBelowModuleRoutes(): void
{
    /** @var RouteCollection $collection */
    $collection = app('router')->getRoutes();
    $route = $collection->getByName('baobab.public.show');

    if ($route === null) {
        return;
    }

    $property = new ReflectionProperty($collection, 'routes');

    /** @var array<string, array<string, Route>> $routesByMethod */
    $routesByMethod = $property->getValue($collection);
    $key = $route->getDomain().$route->uri();

    foreach ($route->methods() as $method) {
        if (isset($routesByMethod[$method][$key])) {
            unset($routesByMethod[$method][$key]);
            $routesByMethod[$method][$key] = $route;
        }
    }

    $property->setValue($collection, $routesByMethod);
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

it('serves the generated front index and show pages publicly, without authentication', function () {
    generateInstallAndBootFleetFrontModule();

    DB::table('cars')->insert(['brand' => 'Renault', 'created_at' => now(), 'updated_at' => now()]);
    $car = DB::table('cars')->first();

    // Régression potentielle : la route publique générique du Core
    // (`{prefix}/{slug?}`) intercepterait cette URL si elle était enregistrée
    // avant les routes du module (cf. commentaire dans
    // BaobabServiceProvider::boot()) — un 404 ici signalerait ce problème
    // d'ordre, pas une absence de route.
    $this->get('/fleet/cars')
        ->assertOk()
        ->assertSee('Renault');

    $this->get("/fleet/cars/{$car->id}")
        ->assertOk()
        ->assertSee('Renault');
});

it('does not generate front routes for an entity that does not opt in', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-front/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'entities' => [[
            'key' => 'Car',
            'table' => 'cars',
            'routes' => ['admin' => false],
            'fields' => [['key' => 'brand', 'type' => 'text', 'required' => true]],
        ]],
    ]));

    app(ModuleGenerator::class)($blueprint);

    expect(File::exists(app(ModuleGenerator::class)->moduleDir('garage-front/fleet').'/routes/web.php'))->toBeFalse();
});
