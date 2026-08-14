<?php

use Baobab\Branding\Actions\ApplyBrandProfile;
use Baobab\Branding\Actions\ResetBrandingToken;
use Baobab\Branding\Actions\ResetBrandingTokens;
use Baobab\Branding\Actions\UpdateBrandingSettings;
use Baobab\Branding\Exceptions\UnknownDesignTokenException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\BrandProfileRegistry;
use Baobab\Branding\Support\DesignTokenSchema;

afterEach(function () {
    resetFontsRegistryStorage();
});

/**
 * Réinitialisation des tokens de marque (spec 18 §8).
 *
 * Le comportement vérifié ici est celui que §3.3 **promet** — « il retombe
 * naturellement sur le thème/profil » — et non ce que sa lettre décrit
 * (« supprimer la clé »), les niveaux 3 et 4 vivant dans une même colonne
 * depuis la Pass B du point 8 (suivi n° 85). Confirmé avec l'utilisateur le
 * 14 août 2026 : profil d'abord, Core seulement en dernier recours.
 */
it('restaure la valeur du profil appliqué plutôt que de franchir le profil', function () {
    app(ApplyBrandProfile::class)('corporate');

    $profile = app(BrandProfileRegistry::class)->load('corporate');

    // L'utilisateur pousse un réglage fin par-dessus le profil.
    app(UpdateBrandingSettings::class)(['tokens' => ['colors' => ['accent' => '#123456']]]);

    expect(BrandingSetting::current()->tokens['colors']['accent'] ?? null)->toBe('#123456');

    $setting = app(ResetBrandingToken::class)('colors', 'accent');

    expect($setting->tokens['colors']['accent'] ?? null)->toBe($profile['colors']['accent']);
});

it('supprime la clé quand aucun profil n\'est appliqué, laissant la cascade répondre', function () {
    app(UpdateBrandingSettings::class)(['tokens' => ['colors' => ['accent' => '#123456']]]);

    $setting = app(ResetBrandingToken::class)('colors', 'accent');

    expect($setting->tokens['colors']['accent'] ?? null)->toBeNull();
});

it('retire le groupe devenu vide plutôt que de laisser un tableau orphelin', function () {
    app(UpdateBrandingSettings::class)(['tokens' => ['radius' => ['sm' => '1px']]]);

    $setting = app(ResetBrandingToken::class)('radius', 'sm');

    expect($setting->tokens)->not->toHaveKey('radius');
});

it('resynchronise primary_color sur la valeur résolue quand la primaire est réinitialisée', function () {
    app(UpdateBrandingSettings::class)(['primary_color' => '#ABCDEF']);

    expect(BrandingSetting::current()->primary_color)->toBe('#ABCDEF');

    $setting = app(ResetBrandingToken::class)('colors', 'primary');

    // Aucun thème actif dans ce contexte : la cascade retombe sur le Core,
    // et l'alias lu par les e-mails doit suivre — pas rester sur l'ancienne
    // valeur, qui n'est plus servie nulle part.
    expect($setting->primary_color)->toBe(DesignTokenSchema::CORE_DEFAULTS['colors']['primary']);
});

it('refuse un token hors du vocabulaire avec une exception typée', function () {
    app(ResetBrandingToken::class)('colors', 'chartreuse');
})->throws(UnknownDesignTokenException::class);

it('refuse un groupe inconnu avec la même exception typée', function () {
    app(ResetBrandingToken::class)('gradients', 'primary');
})->throws(UnknownDesignTokenException::class);

it('vide les surcharges et le profil à la réinitialisation globale', function () {
    app(ApplyBrandProfile::class)('corporate');
    app(UpdateBrandingSettings::class)(['tokens' => ['colors' => ['accent' => '#123456']]]);

    $setting = app(ResetBrandingTokens::class)();

    expect($setting->tokens)->toBe([])
        ->and($setting->brand_profile)->toBeNull()
        ->and($setting->primary_color)->toBe(DesignTokenSchema::CORE_DEFAULTS['colors']['primary']);
});

it('ne touche ni au logo ni au favicon à la réinitialisation globale', function () {
    // §8 range l'identité hors de la cascade de tokens, et personne
    // n'attend de perdre son logo en réinitialisant ses couleurs.
    $setting = BrandingSetting::current();
    $setting->logo_media_id = null;
    $setting->favicon_media_id = null;
    $setting->save();

    app(ApplyBrandProfile::class)('corporate');

    $before = BrandingSetting::current()->only(['logo_media_id', 'favicon_media_id']);

    app(ResetBrandingTokens::class)();

    expect(BrandingSetting::current()->only(['logo_media_id', 'favicon_media_id']))->toBe($before);
});
