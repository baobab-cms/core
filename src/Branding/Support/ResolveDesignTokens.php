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
        $resolved = DesignTokenSchema::CORE_DEFAULTS;

        $theme = $this->themeResolver->current();

        if ($theme !== null) {
            /** @var array<string, array<string, string>> $themeTokens */
            $themeTokens = $theme->manifest['tokens'] ?? [];
            $resolved = $this->mergeGroup($resolved, $themeTokens);
        }

        /** @var array<string, array<string, string>> $adminTokens */
        $adminTokens = BrandingSetting::current()->tokens ?? [];
        $resolved = $this->mergeGroup($resolved, $adminTokens);

        /** @var array<string, array<string, string>> $filtered */
        $filtered = Hook::filter('baobab.branding.tokens', $resolved);

        return $filtered;
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
