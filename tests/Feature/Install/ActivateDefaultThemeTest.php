<?php

use Baobab\Install\Actions\ActivateDefaultTheme;
use Baobab\Modules\Models\Module;

/**
 * Spec 15 §4 étape 7 — l'installation active le thème livré avec la
 * distribution (suivi n° 247, arbitrage D-A).
 *
 * Ce que ces tests gardent avant tout, c'est que l'étape **ne fait jamais
 * échouer une installation** : les pages de repli du Core existent pour
 * l'état « aucun thème actif », et perdre une installation par ailleurs
 * valide parce qu'un thème refuse de s'installer serait échanger un site
 * utilisable contre rien.
 */
beforeEach(function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
});

afterEach(function () {
    removeThemeLink('acme-theme');
});

it('installe et active le thème de la distribution, et le nomme', function () {
    config(['baobab.install.default_theme' => 'acme/theme']);

    $lignes = app(ActivateDefaultTheme::class)();

    $theme = Module::where('name', 'acme/theme')->first();

    expect($theme)->not->toBeNull()
        ->and($theme?->status)->toBe('active')
        ->and($lignes)->toBe(['Thème activé : Acme Theme.']);
});

/**
 * Une reprise repasse par l'étape, et un site peut avoir été installé puis
 * habillé autrement. L'étape ne défait pas un choix qu'elle n'a pas fait.
 */
it('respecte un thème déjà actif au lieu de le remplacer', function () {
    Module::create([
        'name' => 'acme/autre-theme',
        'title' => 'Autre thème',
        'type' => 'theme',
        'version' => '1.0.0',
        'provider' => '',
        'source' => 'local',
        'path' => '/nulle-part',
        'manifest' => [],
        'status' => 'active',
    ]);

    config(['baobab.install.default_theme' => 'acme/theme']);

    $lignes = app(ActivateDefaultTheme::class)();

    expect($lignes)->toBe(['Thème déjà actif : Autre thème.'])
        ->and(Module::where('name', 'acme/theme')->exists())->toBeFalse();
});

/**
 * Le cas d'une distribution amputée — ou d'un `composer install` qui n'a pas
 * tiré le thème. Ce n'est pas une raison de perdre l'installation.
 */
it('ne lève pas quand aucun thème n\'est présent, et dit ce qui se passera', function () {
    config(['baobab.install.default_theme' => 'baobab/theme-absent']);

    $lignes = app(ActivateDefaultTheme::class)();

    expect($lignes)->toHaveCount(2)
        ->and($lignes[0])->toContain('baobab/theme-absent')
        ->and($lignes[1])->toContain('pages de repli')
        ->and(Module::where('type', 'theme')->where('status', 'active')->exists())->toBeFalse();
});

/**
 * Le thème est là mais il est refusé par `ThemeValidator` — capture d'écran
 * ou template obligatoire manquant. L'installation continue quand même, et
 * l'erreur est **dite** plutôt qu'avalée.
 */
it('ne lève pas quand le thème découvert refuse de s\'installer', function () {
    config(['baobab.install.default_theme' => 'acme/theme-invalid']);

    $lignes = app(ActivateDefaultTheme::class)();

    expect($lignes)->toHaveCount(2)
        ->and($lignes[0])->toStartWith('Le thème acme/theme-invalid n\'a pas pu être activé :')
        ->and($lignes[1])->toContain('L\'installation se poursuit')
        ->and(Module::where('type', 'theme')->where('status', 'active')->exists())->toBeFalse();
});
