<?php

use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\ModuleInventory;
use Baobab\Modules\ModuleInventoryEntry;

beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

/**
 * @param  list<ModuleInventoryEntry>  $entries
 */
function entryNamed(array $entries, string $name): ModuleInventoryEntry
{
    foreach ($entries as $entry) {
        if ($entry->name === $name) {
            return $entry;
        }
    }

    throw new RuntimeException("Aucune entrée d'inventaire nommée {$name}.");
}

it('reports a module found on disk but never installed as discovered', function () {
    $entry = entryNamed(app(ModuleInventory::class)->all(), 'acme/blog');

    expect($entry->status)->toBe('discovered')
        ->and($entry->isInstalled())->toBeFalse()
        ->and($entry->onDisk)->toBeTrue()
        ->and($entry->source)->toBe('local')
        ->and($entry->title)->toBe('Blog');
});

it('reports the persisted status once the module is installed, then activated', function () {
    app(InstallModule::class)('acme/blog');

    expect(entryNamed(app(ModuleInventory::class)->all(), 'acme/blog')->status)->toBe('installed');

    app(ActivateModule::class)('acme/blog');

    $entry = entryNamed(app(ModuleInventory::class)->all(), 'acme/blog');

    expect($entry->status)->toBe('active')
        ->and($entry->isActive())->toBeTrue()
        ->and($entry->isInstalled())->toBeTrue()
        ->and($entry->id)->not->toBeNull();
});

it('never lists an installed module twice, even though it is also on disk', function () {
    app(InstallModule::class)('acme/blog');

    $matching = array_filter(
        app(ModuleInventory::class)->all(),
        fn (ModuleInventoryEntry $entry): bool => $entry->name === 'acme/blog',
    );

    expect($matching)->toHaveCount(1);
});

/**
 * Cas réel : quelqu'un supprime le dossier du module sur le serveur. La ligne
 * `modules` survit ; l'écran doit pouvoir dire que le code a disparu plutôt
 * que d'offrir une activation qui échouera.
 */
it('flags an installed module whose files have disappeared from disk', function () {
    app(InstallModule::class)('acme/blog');

    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('composer/*')]]]);

    $entry = entryNamed(app(ModuleInventory::class)->all(), 'acme/blog');

    expect($entry->onDisk)->toBeFalse()
        ->and($entry->isInstalled())->toBeTrue();
});

it('carries the declared dependencies so the screen can show them', function () {
    config(['baobab.modules.paths' => ['composer' => [fixtureModulesPath('composer/*')]]]);

    expect(entryNamed(app(ModuleInventory::class)->all(), 'acme/other')->requires)
        ->toHaveKey('acme/widgets');
});

it('splits the module name into the two route segments', function () {
    expect(entryNamed(app(ModuleInventory::class)->all(), 'acme/blog')->routeParams())
        ->toBe(['vendor' => 'acme', 'slug' => 'blog']);
});

it('sorts by type then title, so themes and modules never interleave', function () {
    $types = array_map(
        fn (ModuleInventoryEntry $entry): string => $entry->type,
        app(ModuleInventory::class)->all(),
    );

    expect($types)->toBe(array_values(collect($types)->sort()->all()));
});
