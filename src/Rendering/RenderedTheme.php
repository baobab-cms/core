<?php

declare(strict_types=1);

namespace Baobab\Rendering;

use Baobab\Modules\Models\Module;

/**
 * Le thème **effectivement rendu** pour la requête courante : celui en préview
 * s'il y en a un (spec 03 §7), sinon le thème actif.
 *
 * Distinct d'`ActiveThemeResolver`, qui répond à une autre question — « quel
 * thème le site sert-il vraiment ? » — et doit continuer d'y répondre pour
 * tout ce qui n'est pas du rendu : la protection de suppression d'une police
 * (`DeleteFont`), la cascade des tokens et le diagnostic « aucun thème actif »
 * de la bande admin n'ont que faire de la préview d'un administrateur.
 *
 * Existe parce que la question se posait à **deux endroits qui répondaient
 * différemment** (n° 125) : `ResolveActiveTheme` montait les vues du thème en
 * préview pendant que `<x-baobab::vite>` émettait les assets du thème actif.
 * La page sortait donc en morceaux — les gabarits du candidat, la feuille de
 * style de l'autre —, ce qu'un utilisateur voit d'abord comme « il manque le
 * CSS ». Un seul point décide désormais, et les deux le lisent.
 *
 * Le repli sur le thème actif tant que personne n'a décidé n'est pas une
 * précaution de style : une vue de thème peut être rendue hors du groupe de
 * routes publiques (rendu direct en test, commande console), là où le
 * middleware n'a jamais tourné.
 */
final class RenderedTheme
{
    private ?Module $theme = null;

    private bool $decided = false;

    public function __construct(private readonly ActiveThemeResolver $active) {}

    /**
     * Appelé une fois par requête par `ResolveActiveTheme`, toujours — y
     * compris avec `null`. Décider systématiquement, plutôt que seulement en
     * préview, évite qu'un thème « colle » d'une requête à la suivante dans un
     * process qui en traite plusieurs (tests, Octane), exactement la précaution
     * que prend déjà `ThemeViewRegistrar` en remplaçant ses chemins.
     */
    public function decide(?Module $theme): void
    {
        $this->theme = $theme;
        $this->decided = true;
    }

    public function current(): ?Module
    {
        return $this->decided ? $this->theme : $this->active->current();
    }
}
