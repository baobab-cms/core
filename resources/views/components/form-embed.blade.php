{{--
    Enveloppe stable, jamais remplacée elle-même : c'est le point d'ancrage du
    swap en mode fragment (Pass C2). Le contenu qu'elle porte (confirmation ou
    formulaire) est, lui, entièrement remplacé à chaque soumission — et porte
    son propre `x-data`, qu'Alpine réinitialise automatiquement à l'insertion
    (observateur de mutations natif, aucun appel manuel nécessaire).
    `aria-live="polite"` : sans rechargement de page, rien d'autre n'annonce
    à un lecteur d'écran qu'un contenu vient de changer.
--}}
<div {{ $attributes }} aria-live="polite">
    <div
        x-data="baobabFormFragment({ mode: @js($mode) })"
        x-init="attach($el)"
    >
        @if ($confirmationMessage !== null)
            <div class="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-foreground" role="status">
                {{ $confirmationMessage }}
            </div>
        @else
            {{-- `enctype` conditionnel (Pass C3) : un formulaire sans champ
                 `file` n'a aucune raison de poster en multipart. `null` fait
                 disparaître l'attribut entier (`ComponentAttributeBag`),
                 jamais un `enctype=""` vide. --}}
            <x-baobab::form method="POST" :action="route('baobab.forms.submit', ['form' => $form->slug])" :enctype="$hasFileField ? 'multipart/form-data' : null">
                <input type="hidden" name="_form_slug" value="{{ $slug }}">
                <input type="hidden" name="_form_rt" value="{{ $renderToken }}">

                {{-- Honeypot (spec 14 §7.1, Pass D1) : un champ bien réel,
                     jamais `type="hidden"` (repéré et ignoré par les robots
                     qui connaissent ce piège précis) — dissimulé visuellement
                     hors écran, retiré de l'ordre de tabulation et de l'arbre
                     d'accessibilité pour qu'aucun visiteur, voyant ou non, ne
                     puisse jamais le remplir par erreur. Nom sans rapport
                     avec le champ `email` du formulaire, pour ne pas prêter à
                     confusion avec de l'auto-remplissage de navigateur. --}}
                <div style="position:absolute; left:-9999px" aria-hidden="true">
                    <input type="text" name="_form_hp" tabindex="-1" autocomplete="off">
                </div>

                <x-baobab::forms.fields :fields="$fields" required />

                <x-baobab::button type="submit" variant="primary">{{ __('baobab::rendering.form_submit_action') }}</x-baobab::button>
            </x-baobab::form>
        @endif
    </div>
</div>

@once
    <script>
        // Mode fragment (spec 14 §4, Pass C2) : dégradation gracieuse par
        // construction, pas par détection — en `mode: 'redirect'` (ou sans
        // JavaScript du tout), cette fonction ne pose jamais d'écouteur et le
        // navigateur poste nativement le formulaire.
        function baobabFormFragment(config) {
            return {
                attach(el) {
                    if (config.mode !== 'fragment') {
                        return;
                    }

                    const form = el.querySelector('form');

                    if (form === null) {
                        return;
                    }

                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Baobab-Form-Fragment': '1' },
                        });

                        el.outerHTML = await response.text();
                    });
                },
            };
        }
    </script>
@endonce
