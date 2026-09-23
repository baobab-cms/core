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

// ── Embedded fonts (spec 18 §5.3, §6.1, M8 point 8 Pass B) ────────────────

it('parses a valid fonts block', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'theme' => ['screenshot' => 'screenshot.png'],
        'fonts' => [
            ['family' => 'Fraunces', 'files' => ['variable' => 'assets/fonts/fraunces-var.woff2'], 'license' => 'OFL-1.1'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect($manifest->fonts())->toBe([
        ['family' => 'Fraunces', 'files' => ['variable' => 'assets/fonts/fraunces-var.woff2'], 'license' => 'OFL-1.1'],
    ]);
});

it('defaults fonts to an empty array when undeclared', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/blog',
        'title' => 'Blog',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\\Blog\\Providers\\BlogServiceProvider',
    ], JSON_THROW_ON_ERROR));

    expect($manifest->fonts())->toBe([]);
});

it('rejects a font declaration missing its required family or files', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'version' => '1.0.0',
        'type' => 'theme',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'theme' => ['screenshot' => 'screenshot.png'],
        'fonts' => [
            ['family' => 'Fraunces'],
        ],
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('parses a privacy.cookies declaration (spec 16 §3.2)', function () {
    $cookie = ['name' => '_stats', 'category' => 'analytics', 'purpose' => 'Mesure d\'audience', 'duration' => '13 mois', 'provider' => 'Acme Stats'];

    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/manifest-cookies',
        'title' => 'Manifest cookies',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\ManifestCookies\Provider',
        'privacy' => ['cookies' => [$cookie]],
    ], JSON_THROW_ON_ERROR));

    expect($manifest->privacyCookies())->toBe([$cookie]);
});

it('defaults privacyCookies to an empty array when undeclared', function () {
    $manifest = ModuleManifest::fromJson(json_encode([
        'name' => 'acme/manifest-no-cookies',
        'title' => 'Manifest no cookies',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\ManifestNoCookies\Provider',
    ], JSON_THROW_ON_ERROR));

    expect($manifest->privacyCookies())->toBe([]);
});

it('rejects a cookie declared in a category outside the closed vocabulary', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/manifest-bad-cookie',
        'title' => 'Manifest bad cookie',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\ManifestBadCookie\Provider',
        'privacy' => ['cookies' => [['name' => '_ads', 'category' => 'advertising', 'purpose' => 'Pub', 'duration' => '1 an']]],
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);

it('rejects a cookie declaration missing its purpose', function () {
    ModuleManifest::fromJson(json_encode([
        'name' => 'acme/manifest-partial-cookie',
        'title' => 'Manifest partial cookie',
        'version' => '1.0.0',
        'type' => 'module',
        'provider' => 'Acme\ManifestPartialCookie\Provider',
        'privacy' => ['cookies' => [['name' => '_stats', 'category' => 'analytics', 'duration' => '13 mois']]],
    ], JSON_THROW_ON_ERROR));
})->throws(InvalidManifestException::class);
