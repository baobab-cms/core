<?php

declare(strict_types=1);

namespace Baobab\Mail;

use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Inlining CSS au moment de l'envoi (spec 13 §3.4, §7 décision 1) — wrapper
 * autour de `tijsverkoyen/css-to-inline-styles` : l'API publique de Baobab
 * est cette classe, le moteur est un détail d'implémentation substituable.
 * Le bloc `<style>` du layout e-mail (spec 13 §3.4) est extrait
 * automatiquement du HTML par le moteur — pas besoin de le passer à part.
 */
final class CssInliner
{
    public function inline(string $html): string
    {
        return (new CssToInlineStyles)->convert($html);
    }
}
