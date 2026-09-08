<?php

use Baobab\Branding\Actions\CompileDesignTokens;
use Baobab\Modules\Models\Module;
use Baobab\Rendering\RenderedTheme;
use Baobab\View\Components\DesignTokens;
use Illuminate\Support\Facades\File;

afterEach(function () {
    foreach (glob(public_path('baobab/tokens-*.css')) ?: [] as $file) {
        File::delete($file);
    }

    resetFontsRegistryStorage();
});

function activeThemeFixture(): Module
{
    return Module::create([
        'name' => 'acme/active-theme',
        'title' => 'Active Theme',
        'type' => 'theme',
        'version' => '1.0.0',
        'provider' => 'Acme\\ActiveTheme\\Providers\\ThemeServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-active-theme',
        'status' => 'active',
        'manifest' => [],
    ]);
}

function candidateThemeFixture(): Module
{
    return Module::create([
        'name' => 'acme/candidate-theme',
        'title' => 'Candidate Theme',
        'type' => 'theme',
        'version' => '1.0.0',
        'provider' => 'Acme\\CandidateTheme\\Providers\\ThemeServiceProvider',
        'source' => 'local',
        'path' => '/tmp/acme-candidate-theme',
        'status' => 'installed',
        'manifest' => [
            'tokens' => [
                'colors' => ['primary' => '#1b7f5a'],
            ],
        ],
    ]);
}

it('serves the compiled artifact of the active theme when no preview is running', function () {
    $active = activeThemeFixture();
    app(RenderedTheme::class)->decide($active);
    $path = app(CompileDesignTokens::class)();

    $component = app(DesignTokens::class);

    expect($component->href)->toBe('/baobab/'.basename($path))
        ->and($component->inlineCss)->toBeNull();
});

/**
 * n° 135 — l'aperçu de tokens restait celui du thème actif, jamais du
 * candidat : `ResolveDesignTokens` lisait `ActiveThemeResolver` plutôt que
 * `RenderedTheme`, et `<x-baobab::design-tokens />` liait de toute façon
 * l'artefact statique sur disque, quel que soit le résultat de la cascade.
 */
it('serves the candidate theme tokens inline during a preview, without touching the active artifact', function () {
    $active = activeThemeFixture();
    app(RenderedTheme::class)->decide($active);
    $activePath = app(CompileDesignTokens::class)();
    $activeCss = File::get($activePath);

    $candidate = candidateThemeFixture();
    app(RenderedTheme::class)->decide($candidate);

    $component = app(DesignTokens::class);

    expect($component->href)->toBeNull()
        ->and($component->inlineCss)->toContain('#1b7f5a')
        ->and(File::get($activePath))->toBe($activeCss)
        ->and(glob(public_path('baobab/tokens-*.css')))->toHaveCount(1);
});
