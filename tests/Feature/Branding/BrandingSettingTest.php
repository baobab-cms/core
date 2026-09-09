<?php

use Baobab\Branding\Actions\UpdateBrandingSettings;
use Baobab\Branding\Models\BrandingSetting;
use Illuminate\Support\Facades\DB;

/**
 * `current()` est censé garantir une ligne unique, toujours `id=1` (docblock
 * du modèle). Bug réel trouvé en recette (9 septembre 2026) : `id` n'étant
 * pas dans `$fillable`, `firstOrNew(['id' => 1])` ne le fixait jamais sur
 * l'instance neuve — `fill()` l'ignore silencieusement — et `save()`
 * insérait une ligne à un id auto-incrémenté au lieu de réoccuper la ligne 1.
 * Une fois la ligne 1 absente (ex. une base réinitialisée), plus aucune
 * sauvegarde n'était jamais relue : chaque tentative créait une ligne
 * orpheline de plus, invisible à l'écran suivant.
 */
it('creates the singleton at id=1 on first save', function () {
    $setting = BrandingSetting::current();
    $setting->primary_color = '#123456';
    $setting->save();

    expect($setting->id)->toBe(1)
        ->and(DB::table('branding_settings')->count())->toBe(1);
});

it('keeps returning the same row across calls once it exists', function () {
    app(UpdateBrandingSettings::class)(['primary_color' => '#123456']);
    app(UpdateBrandingSettings::class)(['primary_color' => '#654321']);

    expect(DB::table('branding_settings')->count())->toBe(1)
        ->and(BrandingSetting::current()->primary_color)->toBe('#654321');
});

it('reoccupies id=1 rather than orphaning a new row when the singleton row is gone', function () {
    app(UpdateBrandingSettings::class)(['primary_color' => '#123456']);

    // Reproduit la panne réelle : la ligne 1 disparaît (reset de base,
    // suppression manuelle...) alors que l'auto-incrément, lui, a déjà
    // avancé — la prochaine ligne créée n'a donc aucune raison de retomber
    // sur id=1 d'elle-même.
    DB::table('branding_settings')->where('id', 1)->delete();
    DB::table('branding_settings')->insert([
        'id' => 6,
        'primary_color' => '#999999',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(UpdateBrandingSettings::class)(['primary_color' => '#abcdef']);

    expect(DB::table('branding_settings')->count())->toBe(2)
        ->and(BrandingSetting::current()->id)->toBe(1)
        ->and(BrandingSetting::current()->primary_color)->toBe('#abcdef');
});
