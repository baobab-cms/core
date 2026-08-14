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
            this.toasts.push({ id, type: toast.type ?? 'info', message: toast.message });
            setTimeout(() => this.dismiss(id), 5000);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter(toast => toast.id !== id);
        },
    }"
    x-init="@if (session('toast')) push(@js(session('toast'))) @endif"
    x-on:toast.window="push($event.detail)"
    class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-on:click="dismiss(toast.id)"
            class="cursor-pointer rounded-md border px-4 py-2 text-sm shadow-lg"
            x-bind:class="{
                'border-success/30 bg-success/10 text-success': toast.type === 'success',
                'border-warning/30 bg-warning/10 text-warning': toast.type === 'warning',
                'border-danger/30 bg-danger/10 text-danger': toast.type === 'danger',
                'border-border bg-surface text-foreground': toast.type === 'info',
            }"
            x-text="toast.message"
        ></div>
    </template>
</div>
