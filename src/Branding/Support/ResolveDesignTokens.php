<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

use Baobab\Branding\Models\BrandingSetting;
use Baobab\Facades\Hook;
use Baobab\Rendering\ActiveThemeResolver;

/**
 * Cascade de résolution des design tokens (spec 18 §3) : défauts Core →
 * `tokens` du thème actif (`theme.json`, niveau 2) → surcharges admin
 * (niveau 4). Le niveau 3 (profil de marque) est Pass B, no-op ici — rien à
 * fusionner tant qu'aucune Action n'écrit `BrandingSetting::brand_profile`.
 * Résolu à l'écriture (§3.2), jamais au rendu — consommé uniquement par
 * `CompileDesignTokens`.
 */
final class ResolveDesignTokens
{
    public function __construct(private readonly ActiveThemeResolver $themeResolver) {}

    /**
     * @return array<string, array<string, string>>
     */
    public function __invoke(): array
    {
        $resolved = $this->baseline();

        /** @var array<string, array<string, string>> $adminTokens */
        $adminTokens = BrandingSetting::current()->tokens ?? [];
        $resolved = $this->mergeGroup($resolved, $adminTokens);

        /** @var array<string, array<string, string>> $filtered */
        $filtered = Hook::filter('baobab.branding.tokens', $resolved);

        return $filtered;
    }

    /**
     * La cascade **sans** les surcharges admin : défauts Core, puis `tokens`
     * du thème actif.
     *
     * C'est la valeur sur laquelle un token retombe quand on le réinitialise,
     * et donc la référence qui permet de dire si un token est *surchargé* —
     * ce que l'écran de marque marque désormais carte par carte (spec 18 §8,
     * suivi n° 149). Extrait de `__invoke()` plutôt que recalculé ailleurs :
     * deux définitions de « niveau inférieur » finiraient par diverger.
     *
     * Le filtre `baobab.branding.tokens` n'est **pas** appliqué ici : il
     * décrit ce qui est servi, pas ce qui est réglé, et un module qui force
     * une couleur au rendu ne doit pas faire passer un token pour surchargé
     * dans l'écran d'administration.
     *
     * @return array<string, array<string, string>>
     */
    public function baseline(): array
    {
        $resolved = DesignTokenSchema::CORE_DEFAULTS;

        $theme = $this->themeResolver->current();

        if ($theme !== null) {
            /** @var array<string, array<string, string>> $themeTokens */
            $themeTokens = $theme->manifest['tokens'] ?? [];
            $resolved = $this->mergeGroup($resolved, $themeTokens);
        }

        return $resolved;
    }

    /**
     * @param  array<string, array<string, string>>  $base
     * @param  array<string, array<string, string>>  $overrides
     * @return array<string, array<string, string>>
     */
    private function mergeGroup(array $base, array $overrides): array
    {
        foreach (DesignTokenSchema::GROUPS as $group => $keys) {
            foreach ($overrides[$group] ?? [] as $key => $value) {
                if (in_array($key, $keys, true)) {
                    $base[$group][$key] = $value;
                }
            }
        }

        return $base;
    }
}
