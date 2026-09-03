<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

use Baobab\Branding\Models\BrandingSetting;
use Baobab\Facades\Hook;
use Baobab\Rendering\ActiveThemeResolver;
use Throwable;

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

        $resolved = $this->mergeGroup($resolved, $this->adminTokens());

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

        // `current()` porte déjà sa propre garde de base : une table `modules`
        // hors d'atteinte y rend `null` plutôt que de lever. Seule la lecture
        // des surcharges d'apparence manquait de la sienne.
        $theme = $this->themeResolver->current();

        if ($theme !== null) {
            /** @var array<string, array<string, string>> $themeTokens */
            $themeTokens = $theme->manifest['tokens'] ?? [];
            $resolved = $this->mergeGroup($resolved, $themeTokens);
        }

        return $resolved;
    }

    /**
     * Les surcharges d'apparence — **ou rien, si la base ne répond pas**.
     *
     * *Trouvé en recette le 3 septembre 2026, sur une archive décompressée.*
     * `layouts/guest.blade.php` rend `<x-baobab::design-tokens />`, donc cette
     * cascade : sur un site dont la base est hors d'atteinte, l'**écran de
     * connexion** mourait d'une exception de base de données. C'est l'écran
     * par lequel on vient réparer un site en panne — il ne peut pas dépendre
     * d'un réglage d'apparence.
     *
     * Le repli n'est pas une dégradation silencieuse d'un résultat : les
     * défauts du Core et ceux du thème sont un rendu **complet et correct**,
     * simplement sans les personnalisations que l'administrateur a choisies.
     * Personne ne remarque qu'un vert n'est pas le sien sur une page qui,
     * autrement, ne s'afficherait pas du tout.
     *
     * @return array<string, array<string, string>>
     */
    private function adminTokens(): array
    {
        try {
            /** @var array<string, array<string, string>> $tokens */
            $tokens = BrandingSetting::current()->tokens ?? [];

            return $tokens;
        } catch (Throwable) {
            return [];
        }
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
