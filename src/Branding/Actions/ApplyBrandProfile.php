<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Exceptions\UnknownBrandProfileException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Support\BrandProfileRegistry;
use Baobab\Facades\Hook;

/**
 * Applique un profil de marque (spec 18 §7.2) — copie, jamais référence : les
 * valeurs du profil remplacent intégralement `BrandingSetting::tokens`
 * (« changer de profil » repart d'une base propre plutôt que de fusionner
 * avec d'anciens réglages fins) et `brand_profile` retient le slug choisi
 * pour le badge « modifié » de l'écran (comparaison avec le profil courant,
 * pas une 3ᵉ colonne de cascade — cf. suivi n° 83/84 et le narrowing associé
 * documenté dans cette Action).
 *
 * Recompile via le même hook que `UpdateBrandingSettings`
 * (`baobab.branding.tokens.saved`, déjà écouté par
 * `BaobabServiceProvider::registerDesignTokenCompilationListener()`) —
 * aucun nouveau listener nécessaire.
 */
final class ApplyBrandProfile
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BrandProfileRegistry $profiles,
    ) {}

    public function __invoke(string $slug): BrandingSetting
    {
        $tokens = $this->profiles->all();

        if (! array_key_exists($slug, $tokens)) {
            throw UnknownBrandProfileException::named($slug);
        }

        $setting = BrandingSetting::current();
        $setting->tokens = $tokens[$slug]['tokens'];
        $setting->brand_profile = $slug;

        if (isset($tokens[$slug]['tokens']['colors']['primary'])) {
            $setting->primary_color = $tokens[$slug]['tokens']['colors']['primary'];
        }

        $setting->save();

        $this->audit->record('branding.profile_applied', $setting, ['profile' => $slug]);

        Hook::action('baobab.branding.tokens.saved', $setting);

        return $setting;
    }
}
