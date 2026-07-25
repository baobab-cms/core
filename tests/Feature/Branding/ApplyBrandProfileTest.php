<?php

use Baobab\Branding\Actions\ApplyBrandProfile;
use Baobab\Branding\Exceptions\UnknownBrandProfileException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\BrandProfileRegistry;

afterEach(function () {
    resetFontsRegistryStorage();
});

it('copies a profile\'s tokens into the branding setting and records the applied slug', function () {
    $setting = app(ApplyBrandProfile::class)('corporate');

    $expected = app(BrandProfileRegistry::class)->load('corporate');

    expect($setting->brand_profile)->toBe('corporate')
        ->and($setting->tokens)->toBe($expected)
        ->and($setting->primary_color)->toBe($expected['colors']['primary']);
});

it('replaces a previously applied profile rather than merging with it', function () {
    app(ApplyBrandProfile::class)('corporate');

    $setting = app(ApplyBrandProfile::class)('minimal');

    $expected = app(BrandProfileRegistry::class)->load('minimal');

    expect($setting->brand_profile)->toBe('minimal')
        ->and($setting->tokens)->toBe($expected);
});

it('throws for an unknown profile slug', function () {
    expect(fn () => app(ApplyBrandProfile::class)('does-not-exist'))
        ->toThrow(UnknownBrandProfileException::class);
});

it('flags the branding setting as modified once a manual token edit diverges from the applied profile', function () {
    app(ApplyBrandProfile::class)('corporate');

    $setting = BrandingSetting::current();
    $setting->tokens = array_replace_recursive($setting->tokens, ['colors' => ['primary' => '#000000']]);
    $setting->save();

    $registry = app(BrandProfileRegistry::class);

    expect($setting->tokens)->not->toBe($registry->load('corporate'));
});

it('lists all 8 core brand profiles', function () {
    $profiles = app(BrandProfileRegistry::class)->all();

    expect(array_keys($profiles))->toEqualCanonicalizing([
        'corporate', 'modern', 'elegant', 'luxury', 'education', 'medical', 'startup', 'minimal',
    ]);
});
