<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Install\ChecklistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * `<x-baobab::install-checklist :items="…">` — la checklist du §7 à l'écran
 * (spec 15 §7, Pass C3b2, adaptateur web du n° 235).
 *
 * **Le composant porte ses propres styles**, dans un `<style>` que sa vue
 * emporte avec elle. Ce n'est pas un caprice : l'écran final est rendu par la
 * requête qui vient de supprimer `public/baobab/install/` (§6.3), donc
 * `wizard.css` **n'existe plus** quand cette page se recharge — c'est
 * l'arbitrage B1 (n° 235). Et le même balisage sert deux chemins : une page
 * autonome sans JavaScript, et un fragment inséré dans le wizard déjà stylé.
 * Des styles portés par le fragment fonctionnent dans les deux cas ; une
 * feuille externe n'en servirait aucun des deux.
 *
 * **Ce que ce composant ne fait pas** : décider de ce qui est dans la liste.
 * `ComposeServerChecklist` compose, une fois, pour les trois adaptateurs
 * (arbitrage A1, n° 229). Ici on affiche — et la seule intelligence tolérée
 * est celle de `html()`, qui rend lisibles les `code` et les **accents** que
 * la console rend, elle, en clair.
 */
final class InstallChecklist extends Component
{
    /**
     * @param  list<ChecklistItem>  $items
     */
    public function __construct(public array $items = []) {}

    /**
     * Le corps d'un item, échappé **puis** décoré.
     *
     * L'ordre est la sécurité elle-même : on échappe tout, et on ne rend
     * ensuite que les deux marques de notre propre convention. Un texte qui
     * contiendrait `<script>` ressort donc inerte, quelle qu'en soit la
     * provenance — et une partie de ces textes porte l'URL que l'utilisateur
     * a saisie à l'étape 5.
     *
     * Les deux marques sont celles que `ComposeServerChecklist` écrit déjà
     * pour la console, où elles restent en clair : la composition est unique,
     * c'est le rendu qui s'adapte à son médium.
     */
    public function html(string $text): HtmlString
    {
        $escaped = e($text);
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;

        return new HtmlString($escaped);
    }

    public function render(): View
    {
        return view('baobab::components.install-checklist');
    }
}
