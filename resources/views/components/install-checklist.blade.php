{{--
    La checklist des tâches serveur (spec 15 §7) — écran final et `baobab:check`
    ont la même liste, ce fragment est celui du navigateur.

    **Les styles voyagent avec le balisage**, et c'est délibéré (arbitrage B1,
    suivi n° 235) : ce fragment est rendu soit dans une page autonome — servie
    par la requête qui vient de supprimer `wizard.css` (§6.3) — soit inséré par
    `wizard.js` dans le wizard encore stylé. Une feuille externe ne servirait ni
    l'un ni l'autre. Les couleurs passent par les variables du wizard quand
    elles existent, avec un repli littéral quand elles n'existent plus.

    Aucune décision ici : `ComposeServerChecklist` compose, le composant de
    classe échappe et décore, cette vue affiche.
--}}
<style>
    /*
        La carte s'élargit quand la checklist arrive. Le wizard tient dans
        32rem parce qu'il ne montre qu'un formulaire à la fois ; la fin est
        son écran le plus dense, et une ligne de cron y défilerait sur trois
        écrans de large. La règle vaut pour le chemin JavaScript, où le
        fragment atterrit dans la carte du wizard ; la page autonome se
        donne la même largeur elle-même.
    */
    .shell {
        max-width: 44rem;
    }

    .bb-checklist {
        margin: 2rem 0 0;
        padding: 0;
        list-style: none;
        counter-reset: bb-task;
    }

    .bb-checklist__item {
        padding: 1.25rem 0;
        border-top: 1px solid var(--bb-border, #DCE3DF);
    }

    .bb-checklist__title {
        margin: 0 0 .4rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--bb-fg, #16211C);
    }

    .bb-checklist__item--task .bb-checklist__title::before {
        counter-increment: bb-task;
        content: counter(bb-task);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        margin-right: .6rem;
        border-radius: 50%;
        background: var(--bb-green, #1E7A54);
        color: #FFFFFF;
        font-size: .8rem;
    }

    .bb-checklist__item--warning .bb-checklist__title {
        color: var(--bb-danger, #A3271F);
    }

    .bb-checklist__item--warning .bb-checklist__title::before {
        content: "!";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        margin-right: .6rem;
        border-radius: 50%;
        background: var(--bb-danger, #A3271F);
        color: #FFFFFF;
        font-size: .85rem;
        font-weight: 700;
    }

    .bb-checklist__body {
        margin: 0;
        color: var(--bb-muted, #5C6B64);
        font-size: .95rem;
    }

    .bb-checklist code,
    .bb-checklist__command,
    .bb-checklist__file {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    }

    /*
        Un gris translucide plutôt qu'un token : il se pose sur un fond clair
        comme sur un fond sombre sans rien savoir de l'un ni de l'autre, et le
        texte garde la couleur du paragraphe qui l'entoure.
    */
    .bb-checklist code {
        padding: .08rem .3rem;
        border-radius: 5px;
        background: rgb(127 127 127 / 22%);
        font-size: .88em;
    }

    /*
        Une ligne de cron se copie entière ou pas du tout : `pre` garde les
        espaces, et le défilement horizontal vaut mieux qu'un retour à la ligne
        que l'utilisateur collerait tel quel dans son panneau.

        **Les deux couleurs sont littérales, et ne doivent pas redevenir des
        tokens.** Ce bloc a d'abord pris `var(--bb-fg)` comme fond : en mode
        sombre ce token s'inverse — il vaut `#E8EFEA` — et le texte `#F4F6F5`
        écrit dessus devenait du blanc sur du blanc. Vu en recette le
        3 septembre 2026, sur les deux lignes de cron et sur le bloc
        `APP_DEBUG`, c'est-à-dire sur tout ce que cet écran demande de copier.
        Une ardoise sombre constante se lit dans les deux thèmes ; le liseré
        la détache du fond quand la page est sombre elle aussi.
    */
    .bb-checklist__command {
        margin: .75rem 0 0;
        padding: .7rem .85rem;
        overflow-x: auto;
        border: 1px solid rgb(255 255 255 / 12%);
        border-radius: 8px;
        background: #16211C;
        color: #F4F6F5;
        font-size: .8rem;
        line-height: 1.5;
        white-space: pre;
        -webkit-user-select: all;
        user-select: all;
    }

    .bb-checklist__caveat {
        margin: .6rem 0 0;
        padding-left: .75rem;
        border-left: 3px solid var(--bb-gold, #E9A13B);
        color: var(--bb-muted, #5C6B64);
        font-size: .85rem;
    }

    .bb-checklist__config {
        margin: .75rem 0 0;
        padding: .6rem .85rem;
        border: 1px solid var(--bb-border, #DCE3DF);
        border-radius: 8px;
    }

    .bb-checklist__config > summary {
        cursor: pointer;
        font-size: .9rem;
        font-weight: 600;
        color: var(--bb-green, #1E7A54);
    }

    .bb-checklist__file {
        margin: .6rem 0 0;
        font-size: .8rem;
        color: var(--bb-muted, #5C6B64);
        word-break: break-all;
    }
</style>

<ol class="bb-checklist">
    @foreach ($items as $item)
        <li class="bb-checklist__item bb-checklist__item--{{ $item->warning ? 'warning' : 'task' }}">
            <p class="bb-checklist__title">{{ $item->title }}</p>
            <p class="bb-checklist__body">{{ $html($item->body) }}</p>

            @if ($item->command !== null)
                <pre class="bb-checklist__command">{{ $item->command }}</pre>
            @endif

            @if ($item->caveat !== null)
                <p class="bb-checklist__caveat">{{ $html($item->caveat) }}</p>
            @endif

            @foreach ($item->files as $file)
                {{--
                    Le §7 veut la configuration **écrite et affichée** : le
                    chemin pour qui la récupère en FTP, le contenu pour qui la
                    lit à l'écran. Replié par défaut, sans quoi trente lignes
                    de règles enterreraient le reste de la checklist.
                --}}
                <details class="bb-checklist__config">
                    <summary>Configuration {{ $file->label }} à appliquer</summary>

                    @if ($file->path === null)
                        <p class="bb-checklist__caveat">Ces règles n'ont pas pu être écrites sur disque&nbsp;: recopiez-les depuis cet écran, ou relancez <code>php artisan baobab:check</code> une fois le dossier de stockage accessible en écriture.</p>
                    @else
                        <p class="bb-checklist__file">Écrite dans&nbsp;: {{ $file->path }}</p>
                    @endif

                    <pre class="bb-checklist__command">{{ $file->contents }}</pre>
                </details>
            @endforeach
        </li>
    @endforeach
</ol>
