<?php

use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Branding\Support\ResolveDesignTokens;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;

it('resolves to Core defaults when no theme is active and no admin override exists', function () {
    $resolved = app(ResolveDesignTokens::class)();

    expect($resolved)->toBe(DesignTokenSchema::CORE_DEFAULTS);
});

it('applies an admin override as a delta, leaving the rest at Core defaults', function () {
    BrandingSetting::create(['tokens' => ['colors' => ['primary' => '#123456']]]);

    $resolved = app(ResolveDesignTokens::class)();

    expect($resolved['colors']['primary'])->toBe('#123456')
        ->and($resolved['colors']['secondary'])->toBe(DesignTokenSchema::CORE_DEFAULTS['colors']['secondary'])
        ->and($resolved['fonts'])->toBe(DesignTokenSchema::CORE_DEFAULTS['fonts']);
});

it('removing an override key falls back naturally to the level below', function () {
    $setting = BrandingSetting::create(['tokens' => ['colors' => ['primary' => '#123456']]]);
    $setting->update(['tokens' => []]);

    $resolved = app(ResolveDesignTokens::class)();

    expect($resolved['colors']['primary'])->toBe(DesignTokenSchema::CORE_DEFAULTS['colors']['primary']);
});

it('lets an active theme override Core defaults at level 2, then admin overrides win at level 4', function () {
    Module::create([
        'name' => 'acme/theme',
        'title' => 'Theme',
        'type' => 'theme',
        'version' => '1.0.0',
        'provider' => 'Acme\\Theme\\Providers\\ThemeServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-theme',
        'status' => 'active',
        'manifest' => [
            'tokens' => [
                'colors' => ['primary' => '#654321', 'secondary' => '#111111'],
            ],
        ],
    ]);

    BrandingSetting::create(['tokens' => ['colors' => ['primary' => '#abcdef']]]);

    $resolved = app(ResolveDesignTokens::class)();

    expect($resolved['colors']['primary'])->toBe('#abcdef') // admin (niveau 4) gagne
        ->and($resolved['colors']['secondary'])->toBe('#111111'); // thème (niveau 2) gagne sur Core
});

it('silently drops an unknown key or group from admin overrides, never crashing', function () {
    BrandingSetting::create(['tokens' => [
        'colors' => ['primary' => '#123456', 'unknown_key' => '#000000'],
        'icons' => ['star' => 'bi-star'],
    ]]);

    $resolved = app(ResolveDesignTokens::class)();

    expect($resolved['colors'])->not->toHaveKey('unknown_key')
        ->and($resolved)->not->toHaveKey('icons')
        ->and($resolved['colors']['primary'])->toBe('#123456');
});

it('applies the baobab.branding.tokens filter last', function () {
    Hook::modify('baobab.branding.tokens', function (array $tokens) {
        $tokens['colors']['primary'] = '#ffffff';

        return $tokens;
    });

    $resolved = app(ResolveDesignTokens::class)();

    expect($resolved['colors']['primary'])->toBe('#ffffff');
});
