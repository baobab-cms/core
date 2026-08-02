<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Admin\Sidebar\SidebarBuilder;
use Baobab\BaobabServiceProvider;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\File;

/**
 * @param  list<string>  $permissions
 */
function fleetMenuActor(array $permissions): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Fleet Menu Actor {$counter}",
        'email' => "fleet-menu-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    foreach ($permissions as $permission) {
        app(GrantPermission::class)($user, $permission);
    }

    return $user;
}

/**
 * Vendor `garage-menus/fleet` distinct des autres suites Studio — voir
 * docblock de `ApiCrudGeneratorTest` pour la collision de namespace PHP que
 * ceci évite (cette suite installe+active réellement son module).
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
});

/**
 * Contrairement aux hooks (A3a) et aux routes admin/front/API (Pass A2), les
 * entrées de menu n'ont besoin d'aucun rejeu de `bootstrapActiveModules()` :
 * `InstallModule::persistMenuItems()` les persiste en base de façon
 * synchrone, au même instant que l'installation elle-même — `SidebarBuilder`
 * les lit directement depuis la table `module_menu_items`, jamais depuis le
 * manifest en mémoire. Aucun câblage Core nouveau n'était donc nécessaire
 * pour cette sous-passe (contrairement à ce qui était anticipé au plan) :
 * la génération se branche sur un mécanisme déjà entièrement fonctionnel.
 */
it('renders a generated admin menu item in the real sidebar for an authorized actor', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-menus/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'menus' => [
            'admin' => [[
                'label' => 'Flotte',
                'icon' => 'bi-truck',
                'route' => 'admin.fleet.cars.index',
                'permission' => 'fleet.cars.view',
                'order' => 5,
            ]],
        ],
    ]));

    app(ModuleGenerator::class)($blueprint);

    app(InstallModule::class)('garage-menus/fleet');
    app(ActivateModule::class)('garage-menus/fleet');

    // Le menu référence une route admin générée par ce même blueprint
    // (`entity.routes.admin`, défaut `true`) — encore inexistante dans le
    // routeur tant que `ModuleServiceProvider::loadModuleRoutes()` n'a pas
    // tourné, cf. docblock `AdminCrudGeneratorTest`.
    $provider = app()->getProvider(BaobabServiceProvider::class);
    (new ReflectionMethod($provider, 'bootstrapActiveModules'))->invoke($provider);

    // Artefact propre à ce rejeu par réflexion (patron déjà rencontré côté
    // routes front, Pass A2) : `RouteCollection::$nameList` (utilisé par
    // `Route::has()`/`route()`, donc par `SidebarBuilder::resolveUrl()`)
    // n'est peuplé qu'au moment de l'ajout de chaque route, avant que le
    // nom complet ne soit finalisé par l'imbrication des groupes
    // (`admin.` + `fleet.cars.` + `index`) — resterait périmé jusqu'à ce
    // qu'un mécanisme du framework le rafraîchisse, qui n'a normalement
    // jamais l'occasion de s'exécuter dans ce rejeu synthétique hors cycle
    // de requête réel.
    app('router')->getRoutes()->refreshNameLookups();

    $authorized = fleetMenuActor(['baobab.admin.access', 'fleet.cars.view']);
    $unauthorized = fleetMenuActor(['baobab.admin.access']);

    $authorizedSidebar = app(SidebarBuilder::class)->build($authorized);
    $item = $authorizedSidebar->first(fn ($item) => $item->label === 'Flotte');

    expect($item)->not->toBeNull()
        ->and($item->icon)->toBe('bi-truck')
        ->and($item->url)->toBe(route('admin.fleet.cars.index'));

    $unauthorizedSidebar = app(SidebarBuilder::class)->build($unauthorized);
    expect($unauthorizedSidebar->first(fn ($item) => $item->label === 'Flotte'))->toBeNull();
});

it('persists no menu item, and renders an empty sidebar, when none is declared', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-menus/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
    ]));

    app(ModuleGenerator::class)($blueprint);

    app(InstallModule::class)('garage-menus/fleet');
    app(ActivateModule::class)('garage-menus/fleet');

    $actor = fleetMenuActor(['baobab.admin.access']);

    expect(app(SidebarBuilder::class)->build($actor)->isEmpty())->toBeTrue();
});
