<div
    x-data="baobabModal(@js($open))"
    x-on:open-modal.window="show = ($event.detail === '{{ $name }}')"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab="trapFocus($event)"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-40 flex items-center justify-center px-4"
>
    <div
        x-show="show"
        x-on:click="show = false"
        class="fixed inset-0 bg-foreground/50"
        aria-hidden="true"
    ></div>

    <div
        x-ref="panel"
        x-show="show"
        role="dialog"
        aria-modal="true"
        tabindex="-1"
        @if ($ariaLabelledby) aria-labelledby="{{ $ariaLabelledby }}" @endif
        @if (! $ariaLabelledby && $ariaLabel) aria-label="{{ $ariaLabel }}" @endif
        @if ($ariaDescribedby) aria-describedby="{{ $ariaDescribedby }}" @endif
        {{ $attributes->class(['relative w-full rounded-lg border border-border bg-surface p-6 shadow-lg', $maxWidthClass]) }}
    >
        {{ $slot }}
    </div>
</div>

{{--
    Piège à focus + restitution (suivi n° 307, constat 2) : partagé par tous les
    usages de `x-baobab::modal` (confirm, media-picker, tout usage futur), donc
    câblé une seule fois ici plutôt que dans chaque composant appelant.
--}}
@once
    <script>
        function baobabModal(open) {
            return {
                show: open,
                previouslyFocused: null,

                init() {
                    this.$watch('show', (show) => {
                        if (show) {
                            this.previouslyFocused = document.activeElement;
                            this.focusWhenVisible();
                        } else if (this.previouslyFocused) {
                            this.previouslyFocused.focus();
                            this.previouslyFocused = null;
                        }
                    });
                },

                {{--
                    Alpine's own x-show (sans x-transition) applique le retrait de
                    `display: none` via un `setTimeout` interne (pour rester compatible
                    avec les gestionnaires `click.outside`), pas via `$nextTick` — un
                    `focus()` appelé depuis `$nextTick` peut donc tomber sur un panneau
                    encore caché. On attend ici que le panneau soit effectivement
                    visible, image par image, avant de le focus.
                --}}
                focusWhenVisible() {
                    if (getComputedStyle(this.$refs.panel).display === 'none') {
                        requestAnimationFrame(() => this.focusWhenVisible());

                        return;
                    }

                    this.focusFirst();
                },

                {{--
                    `input:not([disabled])` seul laisse passer les champs `type="hidden"`
                    (ex. `_token` du `@csrf`) : jamais focusables, un `.focus()` dessus
                    échoue silencieusement et le focus reste hors de la modale (constat
                    utilisateur). Exclu explicitement ici.
                --}}
                focusableElements() {
                    return Array.from(this.$refs.panel.querySelectorAll(
                        'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                    ));
                },

                focusFirst() {
                    const focusable = this.focusableElements();
                    (focusable[0] ?? this.$refs.panel).focus();
                },

                trapFocus(event) {
                    const focusable = this.focusableElements();

                    if (focusable.length === 0) {
                        event.preventDefault();

                        return;
                    }

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (! event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                },
            };
        }
    </script>
@endonce
