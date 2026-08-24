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

        Largeur bornée et centrée : un message d'erreur fait volontiers trois
        lignes, et une bande qui traverse tout l'écran se lit moins bien qu'un
        bloc. `pointer-events-none` sur le conteneur laisse cliquer ce qu'il
        survole, chaque toast rétablissant le sien pour rester congédiable.
    --}}
    class="pointer-events-none fixed inset-x-0 top-4 z-50 mx-auto flex w-full max-w-2xl flex-col items-center gap-2 px-4"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-on:click="dismiss(toast.id)"
            class="pointer-events-auto w-full cursor-pointer rounded-md border px-4 py-2 text-sm shadow-lg"
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
            x-text="toast.message"
        ></div>
    </template>
</div>
