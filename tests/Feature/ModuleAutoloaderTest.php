<?php

use Acme\Autoload\Providers\AutoloadServiceProvider;
use Acme\Blog\Providers\BlogServiceProvider;
use Baobab\Modules\Models\Module;
use Baobab\Modules\ModuleAutoloader;
use Baobab\Modules\ModuleDiscovery;

it("registers a module's declared autoload.psr-4 mapping so its classes become loadable", function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $discovered = app(ModuleDiscovery::class)->scan()->get('acme/autoload');

    if ($discovered === null) {
        throw new RuntimeException('Expected the acme/autoload fixture to be discovered.');
    }

    $module = Module::create([
        'name' => $discovered->manifest->name(),
        'title' => $discovered->manifest->title(),
        'type' => $discovered->manifest->type(),
        'version' => $discovered->manifest->version(),
        'provider' => $discovered->manifest->provider(),
        'source' => $discovered->source,
        'path' => $discovered->path,
        'manifest' => $discovered->manifest->toArray(),
        'status' => 'active',
    ]);

    // Ne pas appeler class_exists() avant l'enregistrement : Composer met en
    // cache négativement les classes introuvables (ClassLoader::$missingClasses)
    // et n'invalide jamais ce cache sur un addPsr4() ultérieur — un simple
    // class_exists() "avant" empoisonnerait le test, pas seulement l'assertion.
    app(ModuleAutoloader::class)->registerFor($module);

    expect(class_exists(AutoloadServiceProvider::class))->toBeTrue();
});

it('does nothing for a module without a declared autoload mapping', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);

    $discovered = app(ModuleDiscovery::class)->scan()->get('acme/blog');

    if ($discovered === null) {
        throw new RuntimeException('Expected the acme/blog fixture to be discovered.');
    }

    $module = Module::create([
        'name' => $discovered->manifest->name(),
        'title' => $discovered->manifest->title(),
        'type' => $discovered->manifest->type(),
        'version' => $discovered->manifest->version(),
        'provider' => $discovered->manifest->provider(),
        'source' => $discovered->source,
        'path' => $discovered->path,
        'manifest' => $discovered->manifest->toArray(),
        'status' => 'active',
    ]);

    app(ModuleAutoloader::class)->registerFor($module);

    expect(class_exists(BlogServiceProvider::class))->toBeFalse();
});
