<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\BaobabServiceProvider;
use Baobab\Hooks\HookRegistry;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Illuminate\Support\Facades\File;

/**
 * Vendor `garage-hooks/fleet` distinct des autres suites Studio (`garage/fleet`,
 * `garage-api/fleet`, `garage-front/fleet`) — voir docblock de
 * `ApiCrudGeneratorTest` pour la collision de namespace PHP que ceci évite
 * (cette suite installe+active réellement son module, donc `require` les
 * classes générées, comme les trois autres).
 */
beforeEach(function () {
    File::deleteDirectory(generatedModulesPath());
    config(['baobab.studio.modules_path' => generatedModulesPath()]);
    config(['baobab.content_types.modules_path' => generatedModulesPath()]);
    config(['baobab.modules.paths' => ['local' => [generatedModulesPath().'/*']]]);
    unset($GLOBALS['baobab_test_hook_listener_fired']);
});

afterEach(function () {
    File::deleteDirectory(generatedModulesPath());
    unset($GLOBALS['baobab_test_hook_listener_fired']);
});

it('wires a generated hook listener skeleton into the real HookRegistry, invoked when the hook actually fires', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-hooks/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'hooks' => ['listens' => ['garage.fleet.car.serviced' => 'RecordCarServiced']],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $listenerPath = app(ModuleGenerator::class)->moduleDir('garage-hooks/fleet').'/src/Hooks/RecordCarServiced.php';

    expect(File::isFile($listenerPath))->toBeTrue();

    // Le Studio génère un squelette vide (spec §5.1, « rien de magique ») —
    // simule le développeur qui le complète à la main avant l'installation,
    // seule façon d'observer que le câblage réel (pas seulement le fichier
    // généré) fonctionne bout-en-bout.
    File::put($listenerPath, <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace GarageHooks\Fleet\Hooks;

        final class RecordCarServiced
        {
            public function __invoke(mixed ...$args): void
            {
                $GLOBALS['baobab_test_hook_listener_fired'] = $args;
            }
        }

        PHP);

    app(InstallModule::class)('garage-hooks/fleet');
    app(ActivateModule::class)('garage-hooks/fleet');

    $provider = app()->getProvider(BaobabServiceProvider::class);
    (new ReflectionMethod($provider, 'bootstrapActiveModules'))->invoke($provider);

    expect($GLOBALS['baobab_test_hook_listener_fired'] ?? null)->toBeNull();

    app(HookRegistry::class)->action('garage.fleet.car.serviced', 'a-value');

    expect($GLOBALS['baobab_test_hook_listener_fired'] ?? null)->toBe(['a-value']);
});

it('does not generate any file for a hook declared under emits only', function () {
    $blueprint = ModuleBlueprint::fromJson(moduleBlueprintJson([
        'identity' => ['name' => 'garage-hooks/fleet', 'title' => 'Fleet', 'version' => '1.0.0', 'type' => 'module'],
        'hooks' => ['emits' => ['garage.fleet.car.serviced']],
    ]));

    app(ModuleGenerator::class)($blueprint);

    $hooksDir = app(ModuleGenerator::class)->moduleDir('garage-hooks/fleet').'/src/Hooks';

    expect(File::isDirectory($hooksDir))->toBeFalse();
});
