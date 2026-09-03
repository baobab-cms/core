{{--
    La fin de l'installation — **le seul endroit où elle est écrite**.

    Ce fragment est rendu par deux chemins qui n'ont rien en commun sauf lui :
    la page autonome `install/finished.blade.php` quand le navigateur n'exécute
    pas de JavaScript, et `wizard.js` qui l'insère dans l'écran de progression
    quand il en exécute (arbitrage D1, suivi n° 238). Le rendre deux fois — une
    en Blade, une en JavaScript — aurait garanti que les deux divergent, ce que
    l'action unique du §7 existe précisément pour empêcher.

    Il n'utilise donc que des classes définies **des deux côtés** : `.lede`,
    `.actions` et `.button` par `wizard.css` sur le chemin JavaScript, par les
    styles en ligne de la page autonome sur l'autre. Ce que ces deux feuilles
    n'ont pas — la checklist elle-même — voyage dans le composant.
--}}
<p class="lede">Votre site est installé. L'installateur vient de se refermer&nbsp;: cette adresse ne répondra plus.</p>

<p class="actions">
    <a class="button button--link" href="{{ $adminUrl }}">Aller à l'administration</a>
</p>

<p class="hint">
    Connectez-vous avec l'adresse e-mail et le mot de passe que vous venez de
    choisir. Il reste ensuite quelques tâches que l'installateur ne peut pas
    faire à votre place&nbsp;: les voici, et <strong>elles ne réapparaîtront
    plus ici</strong> — gardez cette page, ou relancez-les à tout moment avec
    <code>php artisan baobab:check</code>.
</p>

<x-baobab::install-checklist :items="$items" />
