<?php

use Baobab\Modules\Exceptions\InvalidManifestException;
use Baobab\Modules\ModuleManifest;

it('parses a valid manifest', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
        'requires' => [
            'cms' => '^1.0',
            'modules' => ['acme/media' => '^1.0'],
        ],
        'hooks' => [
            'listens' => ['baobab.booted' => 'Acme\\Blog\\Hooks\\OnBooted'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect($manifest->name())->toBe('acme/blog')
        ->and($manifest->version())->toBe('1.0.0')
        ->and($manifest->type())->toBe('module')
        ->and($manifest->requiresCms())->toBe('^1.0')
        ->and($manifest->requiresModules())->toBe(['acme/media' => '^1.0'])
        ->and($manifest->hooksListened())->toBe(['baobab.booted' => 'Acme\\Blog\\Hooks\\OnBooted']);
});

it('rejects a manifest missing a required field', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/broken',
        'title' => 'Broken',
        'version' => '1.0.0',
        'type' => 'module',
        // provider manquant
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('rejects a manifest with an invalid name pattern', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'NotComposerStyle',
        'title' => 'Broken',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Broken\\Providers\\BrokenServiceProvider',
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('rejects a theme manifest missing the theme block', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('rejects malformed JSON', function () {
    ModuleManifest::fromJson('{not json');
})->throws(InvalidManifestException::class);
