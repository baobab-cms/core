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

it('parses a declared autoload.psr-4 mapping', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
        'autoload' => [
            'psr-4' => ['Acme\\Blog\\' => 'src/'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect($manifest->autoloadPsr4())->toBe(['Acme\\Blog\\' => 'src/']);
});

it('defaults autoloadPsr4 to an empty array when undeclared', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
    ], JSON_THROW_ON_ERROR));

    expect($manifest->autoloadPsr4())->toBe([]);
});

it('parses a declared schedule field', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
        'schedule' => [
            ['key' => 'acme.blog.digest', 'command' => 'acme:blog:digest', 'cron' => '0 8 * * *'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect($manifest->scheduledTasks())->toBe([
        ['key' => 'acme.blog.digest', 'command' => 'acme:blog:digest', 'cron' => '0 8 * * *'],
    ]);
});

it('rejects a schedule entry missing the cron field', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
        'schedule' => [
            ['key' => 'acme.blog.digest', 'command' => 'acme:blog:digest'],
        ],
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

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

// ── Design tokens (spec 18 §6.1) ──────────────────────────────────────────

it('parses a valid tokens block', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'theme' => ['screenshot' => 'screenshot.png'],
        'tokens' => [
            'colors' => ['primary' => '#112233'],
            'fonts' => ['body' => 'Inter'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect($manifest->tokens())->toBe([
        'colors' => ['primary' => '#112233'],
        'fonts' => ['body' => 'Inter'],
    ]);
});

it('defaults tokens to an empty array when undeclared', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
    ], JSON_THROW_ON_ERROR));

    expect($manifest->tokens())->toBe([]);
});

it('rejects an unknown key inside a known tokens group', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'theme' => ['screenshot' => 'screenshot.png'],
        'tokens' => [
            'colors' => ['primary' => '#112233', 'unknown_key' => '#000000'],
        ],
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('rejects an unknown top-level tokens group', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'theme' => ['screenshot' => 'screenshot.png'],
        'tokens' => [
            'icons' => ['star' => 'bi-star'],
        ],
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('rejects a reserved dark tokens group with a message naming it explicitly', function () {
    expect(fn () => ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'theme' => ['screenshot' => 'screenshot.png'],
        'tokens' => [
            'dark' => [],
        ],
    ], JSON_THROW_ON_ERROR)))->toThrow(InvalidManifestException::class, 'dark');
});
