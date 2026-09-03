<?php

use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Branding\Support\ResolveDesignTokens;
use Baobab\Facades\Hook;
use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\Schema;

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

/**
 * **La cascade survit à une base absente** — recette du 3 septembre 2026,
 * suivi n° 242.
 *
 * `layouts/guest.blade.php` rend `<x-baobab::design-tokens />`, donc cette
 * cascade : sans garde, l'écran de connexion mourait d'une exception de base
 * de données dès que celle-ci était hors d'atteinte. C'est l'écran par lequel
 * on vient réparer un site en panne — il ne peut pas dépendre d'un réglage
 * d'apparence.
 *
 * Ce que le repli rend n'est pas dégradé : les défauts du Core sont un rendu
 * complet, simplement sans les personnalisations choisies par l'administrateur.
 */
it('falls back to the levels below when the branding table cannot be read', function () {
    Schema::drop('branding_settings');

    expect(app(ResolveDesignTokens::class)())->toBe(DesignTokenSchema::CORE_DEFAULTS);
});
