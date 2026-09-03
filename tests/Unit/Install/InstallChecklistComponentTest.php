<?php

use Baobab\View\Components\InstallChecklist;

/**
 * Le rendu de la checklist à l'écran (spec 15 §7, Pass C3b2).
 *
 * **Ce que ces tests gardent, c'est l'ordre des deux gestes** : on échappe
 * d'abord, on décore ensuite. `wizard.js` insère ce fragment par `innerHTML`
 * (arbitrage D1, n° 238) — c'est tenable **parce que** Blade échappe, et cesse
 * de l'être à la seconde où quelqu'un décorerait avant d'échapper. Une partie
 * de ces textes porte l'URL saisie à l'étape 5 : elle vient de l'utilisateur,
 * pas du code.
 */
it('échappe le texte avant de le décorer', function () {
    $composant = new InstallChecklist;

    expect($composant->html('<script>alert(1)</script>')->toHtml())
        ->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('rend les deux marques de la convention, et pas une de plus', function () {
    $composant = new InstallChecklist;

    expect($composant->html('corrigez `APP_URL` dans le `.env`')->toHtml())
        ->toBe('corrigez <code>APP_URL</code> dans le <code>.env</code>')
        ->and($composant->html('**le contenu** de votre fichier')->toHtml())
        ->toBe('<strong>le contenu</strong> de votre fichier')
        ->and($composant->html('un [lien](http://ailleurs) markdown')->toHtml())
        ->toBe('un [lien](http://ailleurs) markdown');
});

/**
 * Le cas qui compte : une balise glissée **dans** une marque de la convention
 * reste inerte, parce qu'elle a été échappée avant que la marque ne soit lue.
 */
it('ne laisse pas une balise passer par une marque de la convention', function () {
    expect((new InstallChecklist)->html('`<img src=x onerror=alert(1)>`')->toHtml())
        ->toBe('<code>&lt;img src=x onerror=alert(1)&gt;</code>');
});
