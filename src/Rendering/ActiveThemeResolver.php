<?php

declare(strict_types=1);

namespace Baobab\Rendering;

use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lecture minimale du thème actif (spec 03 §7) — le cycle de vie complet
 * (exclusivité imposée, activation atomique, hooks, préview) est le contrat
 * de thème (M6 point 2). Ici : le premier module `type=theme` avec
 * `status=active`, le système de modules générique (M1) gérant déjà
 * install/activate.
 */
final class ActiveThemeResolver
{
    /**
     * **Pas de base, pas de thème actif** — et surtout pas d'erreur.
     *
     * Ce résolveur est appelé par `ResolveActiveTheme`, qui tourne sur **toute**
     * requête publique. Sur une archive fraîchement décompressée il n'y a pas
     * encore de base : sans cette garde, la page d'accueil répondait 500 avant
     * même que l'utilisateur n'atteigne l'installateur. Constaté en recette sur
     * hébergement réel le 28 août 2026 (suivi n° 224).
     *
     * La garde porte sur la **table** plutôt que sur le lock d'installation :
     * ce qui manque ici n'est pas une installation terminée, c'est de quoi
     * lire. Elle couvre du même geste une base momentanément injoignable —
     * mieux vaut servir la page avec le thème de repli que ne rien servir.
     * C'est le patron de `BaobabServiceProvider::bootstrapActiveModules()`.
     */
    public function current(): ?Module
    {
        try {
            if (! Schema::hasTable('modules')) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return Module::where('type', 'theme')->where('status', 'active')->first();
    }
}
