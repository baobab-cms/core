<?php

use Baobab\Branding\Actions\CompileDesignTokens;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Facades\Hook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

afterEach(function () {
    foreach (glob(public_path('baobab/tokens-*.css')) ?: [] as $file) {
        File::delete($file);
    }

    resetFontsRegistryStorage();
});

it('writes a CSS artifact containing every group and key of the vocabulary', function () {
    $path = app(CompileDesignTokens::class)();

    expect(File::exists($path))->toBeTrue();

    $css = File::get($path);

    foreach (DesignTokenSchema::GROUPS as $group => $keys) {
        foreach ($keys as $key) {
            expect($css)->toContain(DesignTokenSchema::cssVar($group, $key));
        }
    }
});

it('does not rewrite the artifact when the resolved tokens are unchanged', function () {
    $path = app(CompileDesignTokens::class)();
    $mtime = filemtime($path);

    $secondPath = app(CompileDesignTokens::class)();

    expect($secondPath)->toBe($path)
        ->and(filemtime($path))->toBe($mtime);
});

it('produces a new hash and deletes the old artifact when a token changes', function () {
    $firstPath = app(CompileDesignTokens::class)();

    BrandingSetting::create(['tokens' => ['colors' => ['primary' => '#123456']]]);
    $secondPath = app(CompileDesignTokens::class)();

    expect($secondPath)->not->toBe($firstPath)
        ->and(File::exists($secondPath))->toBeTrue()
        ->and(File::exists($firstPath))->toBeFalse();
});

it('fires baobab.branding.compiled with the previous and new hash', function () {
    app(CompileDesignTokens::class)();

    $captured = [];
    Hook::listen('baobab.branding.compiled', function (?string $previous, string $new) use (&$captured) {
        $captured = [$previous, $new];
    });

    BrandingSetting::create(['tokens' => ['colors' => ['primary' => '#123456']]]);
    $path = app(CompileDesignTokens::class)();

    expect($captured[0])->not->toBeNull()
        ->and($path)->toContain($captured[1]);
});

it('renders a stylesheet link when the artifact already exists', function () {
    app(CompileDesignTokens::class)();

    $html = Blade::render('<x-baobab::design-tokens />');

    expect($html)->toContain('<link rel="stylesheet"')
        ->and($html)->toContain('/baobab/tokens-');
});

it('includes @font-face rules for the 3 bundled identity fonts by default', function () {
    $path = app(CompileDesignTokens::class)();
    $css = File::get($path);

    expect($css)->toContain("font-family: 'Bricolage Grotesque Variable'")
        ->and($css)->toContain("font-family: 'Figtree Variable'")
        ->and($css)->toContain("font-family: 'JetBrains Mono'")
        ->and($css)->toContain('font-display: swap')
        ->and(is_file(public_path('baobab/fonts/bricolage-grotesque/bricolage-grotesque-variable.woff2')))->toBeTrue();
});

it('falls back to an inline style and regenerates the artifact when it is missing', function () {
    foreach (glob(public_path('baobab/tokens-*.css')) ?: [] as $file) {
        File::delete($file);
    }

    $html = Blade::render('<x-baobab::design-tokens />');

    expect($html)->toContain('<style>')
        ->and($html)->toContain(DesignTokenSchema::cssVar('colors', 'primary'));

    expect(glob(public_path('baobab/tokens-*.css')))->not->toBeEmpty();
});
