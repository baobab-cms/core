{{--
    Aucune table de variantes côté serveur : le type d'un toast n'est connu
    qu'au moment où il est poussé dans la pile Alpine, donc les classes se
    choisissent côté client, dans le `x-bind:class` ci-dessous. Un tableau
    `$variants` a existé ici jusqu'au 14 août 2026 sans être lu par personne —
    du code mort, supprimé avec la passe de conformité (suivi n° 138).
--}}
<div
    x-data="{
        toasts: [],
        push(toast) {
            const id = Date.now() + Math.random();
            const type = toast.type ?? 'info';
            this.toasts.push({ id, type, message: toast.message });

            // Une confirmation s'efface d'elle-même, un problème attend qu'on
            // l'ait lu. Les erreurs et les avertissements demandent une action
            // — corriger un blueprint, reprendre un champ — et leur message
            // dit *quoi* corriger : le faire disparaître au bout de cinq
            // secondes revenait à ne pas l'avoir écrit (relevé en vérification
            // du chantier 2, suivi n° 205).
            if (type !== 'danger' && type !== 'warning') {
                setTimeout(() => this.dismiss(id), 5000);
            }
        },
        dismiss(id) {
            this.toasts = this.toasts.filter(toast => toast.id !== id);
        },
    }"
    x-init="@if (session('toast')) push(@js(session('toast'))) @endif"
    x-on:toast.window="push($event.detail)"
    {{--
        En haut plutôt qu'en bas (demandé le 24 août 2026, suivi n° 205) : en
        bas à droite, un message long chevauchait le pied de la sidebar et se
        lisait mal. Le regard revient en haut après une action, pas en bas.

        **Dans le flux**, et placé par le layout juste après les bandeaux
        système : en `fixed`, le toast masquait l'avertissement d'environnement
        non-production. Aucune position hors flux n'est nécessaire ici — le
        haut de la page admin ne défile pas, seul `<main>` le fait.

        Largeur bornée et centrée : un message d'erreur fait volontiers trois
        lignes, et une bande qui traverse tout l'écran se lit moins bien qu'un
        bloc. Le conteneur n'occupe aucune hauteur tant qu'il est vide, les
        marges vivant sur les toasts eux-mêmes.
    --}}
    class="relative z-40 mx-auto flex w-full max-w-2xl flex-col items-center gap-2 px-4"
>
    <template x-for="toast in toasts" :key="toast.id">
        {{--
            `role`/`aria-live` distincts selon le type (suivi n° 307 constat 4) :
            `alert` (assertif) pour `danger`/`warning`, qui ne s'effacent jamais
            seuls et méritent une interruption ; `status`/`polite` pour
            `info`/`success`, qui disparaissent d'eux-mêmes et ne doivent pas
            couper la parole à ce que l'utilisateur écoute déjà.
        --}}
        <div
            x-show="true"
            x-bind:role="toast.type === 'danger' || toast.type === 'warning' ? 'alert' : 'status'"
            x-bind:aria-live="toast.type === 'danger' || toast.type === 'warning' ? 'assertive' : 'polite'"
            class="flex w-full items-start gap-3 rounded-md border px-4 py-2 text-sm shadow-lg first:mt-2 last:mb-2"
            {{--
                Fonds pleins et paliers 50/600-700, comme `<x-baobab::badge>` :
                les opacités qui vivaient ici sont précisément ce que le n° 84
                a corrigé ailleurs (spec 18 §13.4, règle 2). Sans objet tant
                que le toast flottait sur le fond de page ; il recouvre
                désormais du contenu, et une translucidité y devient illisible.
            --}}
            x-bind:class="{
                'border-leaf-200 bg-leaf-50 text-leaf-600': toast.type === 'success',
                'border-warning-200 bg-warning-50 text-warning-700': toast.type === 'warning',
                'border-danger-200 bg-danger-50 text-danger-700': toast.type === 'danger',
                'border-border bg-surface text-foreground': toast.type === 'info',
            }"
        >
            <span class="flex-1" x-text="toast.message"></span>

            {{--
                Un vrai `<button>` plutôt que le `<div x-on:click>` d'avant
                (suivi n° 307 constat 4) : les toasts `danger`/`warning` ne
                disparaissent jamais seuls (suivi n° 205), leur seule fermeture
                ne peut pas dépendre de la souris.
            --}}
            <button
                type="button"
                x-on:click="dismiss(toast.id)"
                aria-label="{{ __('baobab::admin.components.close') }}"
                class="flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded opacity-70 hover:opacity-100"
            >&#10005;</button>
        </div>
    </template>
</div>
