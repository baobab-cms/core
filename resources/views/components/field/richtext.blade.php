@props([
    'name',
    'label' => null,
    'value' => null,
    /*
        Menu d'insertion optionnel : nom de variable → libellé. Fourni, un
        sélecteur apparaît dans la barre d'outils et insère le placeholder au
        curseur — l'intégrateur ne tape jamais les accolades à la main (spec 13
        §3.4). Absent, le composant est exactement celui d'avant : c'est ce qui
        permet de l'enrichir sans toucher les écrans de contenu qui l'utilisent
        déjà.
    */
    'variables' => [],
    /*
        Menu d'insertion optionnel : slug de formulaire → titre. Fourni, un
        sélecteur apparaît dans la barre d'outils et insère un noeud Tiptap
        dédié (survit au copier-coller, contrairement aux variables mail
        insérées en texte brut ci-dessus — patron distinct assumé, M8
        point 6 Pass C4) ; absent, aucune extension supplémentaire n'est
        chargée et le composant reste identique à avant.
    */
    'forms' => [],
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div
        x-data="richTextEditor({
            initialValue: @js(old($name, $value)),
            forms: @js($forms !== []),
            hasError: @js($errors->has($name)),
            errorId: @js($name.'-error'),
        })"
        x-init="mount($refs.editorRoot, $refs.hiddenInput)"
        @class([
            'overflow-hidden rounded-md border',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ])
    >
        <div class="flex flex-wrap items-center gap-1 border-b border-border bg-surface-subtle px-2 py-1">
            <button type="button" x-on:click="toggleBold()" x-bind:class="{ 'bg-surface': active.bold }" class="rounded px-2 py-1 text-xs font-semibold hover:bg-surface max-md:min-h-11 max-md:min-w-11">B</button>
            <button type="button" x-on:click="toggleItalic()" x-bind:class="{ 'bg-surface': active.italic }" class="rounded px-2 py-1 text-xs italic hover:bg-surface max-md:min-h-11 max-md:min-w-11">I</button>
            <button type="button" x-on:click="toggleUnderline()" x-bind:class="{ 'bg-surface': active.underline }" class="rounded px-2 py-1 text-xs underline hover:bg-surface">U</button>
            <button type="button" x-on:click="toggleStrike()" x-bind:class="{ 'bg-surface': active.strike }" class="rounded px-2 py-1 text-xs line-through hover:bg-surface">S</button>
            <button type="button" x-on:click="toggleHighlight()" x-bind:class="{ 'bg-surface': active.highlight }" class="rounded px-2 py-1 text-xs hover:bg-surface">
                {{ __('baobab::admin.components.richtext_highlight') }}
            </button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" x-on:click="toggleHeading(2)" x-bind:class="{ 'bg-surface': active.h2 }" class="rounded px-2 py-1 text-xs font-medium hover:bg-surface">H2</button>
            <button type="button" x-on:click="toggleHeading(3)" x-bind:class="{ 'bg-surface': active.h3 }" class="rounded px-2 py-1 text-xs font-medium hover:bg-surface">H3</button>
            <button type="button" x-on:click="toggleHeading(4)" x-bind:class="{ 'bg-surface': active.h4 }" class="rounded px-2 py-1 text-xs font-medium hover:bg-surface">H4</button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" x-on:click="setAlign('left')" x-bind:class="{ 'bg-surface': active.alignLeft }" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="{{ __('baobab::admin.components.richtext_align_left') }}">&#8676;</button>
            <button type="button" x-on:click="setAlign('center')" x-bind:class="{ 'bg-surface': active.alignCenter }" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="{{ __('baobab::admin.components.richtext_align_center') }}">&#8677;</button>
            <button type="button" x-on:click="setAlign('right')" x-bind:class="{ 'bg-surface': active.alignRight }" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="{{ __('baobab::admin.components.richtext_align_right') }}">&#8678;</button>
            <button type="button" x-on:click="setAlign('justify')" x-bind:class="{ 'bg-surface': active.alignJustify }" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="{{ __('baobab::admin.components.richtext_align_justify') }}">&#8679;</button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" x-on:click="toggleBulletList()" x-bind:class="{ 'bg-surface': active.bulletList }" class="rounded px-2 py-1 text-xs hover:bg-surface max-md:min-h-11 max-md:min-w-11">
                {{ __('baobab::admin.components.richtext_bullet_list') }}
            </button>
            <button type="button" x-on:click="toggleOrderedList()" x-bind:class="{ 'bg-surface': active.orderedList }" class="rounded px-2 py-1 text-xs hover:bg-surface max-md:min-h-11 max-md:min-w-11">
                {{ __('baobab::admin.components.richtext_ordered_list') }}
            </button>
            <button type="button" x-on:click="toggleBlockquote()" x-bind:class="{ 'bg-surface': active.blockquote }" class="rounded px-2 py-1 text-xs hover:bg-surface">&rdquo;</button>
            <button type="button" x-on:click="setHorizontalRule()" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="{{ __('baobab::admin.components.richtext_horizontal_rule') }}">&#8213;</button>
            <button type="button" x-on:click="setLink()" x-bind:class="{ 'bg-surface': active.link }" class="rounded px-2 py-1 text-xs underline hover:bg-surface">
                {{ __('baobab::admin.components.richtext_link') }}
            </button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" x-on:click="clearFormat()" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="{{ __('baobab::admin.components.richtext_clear_format') }}">&#10005;</button>
            <button type="button" x-on:click="undo()" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="Annuler">&#8630;</button>
            <button type="button" x-on:click="redo()" class="rounded px-2 py-1 text-xs hover:bg-surface" aria-label="Refaire">&#8631;</button>

            @if ($variables !== [])
                <span class="mx-1 h-4 w-px bg-border"></span>
                <select
                    x-on:change="insertVariable($event.target.value); $event.target.value = ''"
                    class="rounded border border-border bg-surface px-2 py-1 text-xs text-foreground"
                    aria-label="{{ __('baobab::admin.mails.insert') }}"
                >
                    <option value="">{{ __('baobab::admin.mails.insert') }}</option>
                    @foreach ($variables as $variableName => $variableLabel)
                        <option value="{{ $variableName }}">{{ $variableLabel }}</option>
                    @endforeach
                </select>
            @endif

            @if ($forms !== [])
                <span class="mx-1 h-4 w-px bg-border"></span>
                <select
                    x-on:change="insertForm($event.target.value, $event.target.selectedOptions[0]?.dataset.label); $event.target.value = ''"
                    class="rounded border border-border bg-surface px-2 py-1 text-xs text-foreground"
                    aria-label="{{ __('baobab::admin.components.richtext_insert_form') }}"
                >
                    <option value="">{{ __('baobab::admin.components.richtext_insert_form') }}</option>
                    @foreach ($forms as $formSlug => $formTitle)
                        <option value="{{ $formSlug }}" data-label="{{ $formTitle }}">{{ $formTitle }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div x-ref="editorRoot" class="tiptap px-3 py-2 text-sm text-foreground" data-tiptap-editor></div>

        <textarea id="{{ $name }}" name="{{ $name }}" x-ref="hiddenInput" class="hidden" {{ $attributes }}>{{ old($name, $value) }}</textarea>
    </div>

    <x-baobab::field.error :name="$name" />
</div>

@once
    <script>
        function richTextEditor(config) {
            // L'instance Tiptap/ProseMirror ne doit JAMAIS être une propriété
            // réactive Alpine (x-data la passe dans un Proxy) : ProseMirror
            // compare des références d'état internes (transaction.doc vs
            // view.state.doc) et le Proxy réactif casse cette égalité de
            // référence, provoquant "Applying a mismatched transaction" au
            // moindre appel de commande. Gardée dans une fermeture, jamais
            // assignée à `this`.
            let editor = null;

            return {
                active: {},

                mount(root, hiddenInput) {
                    // L'extension d'embed n'est chargée que si un sélecteur de
                    // formulaires a été fourni (prop `forms`) : un champ
                    // richtext de la messagerie ou des réglages de widget n'en
                    // affiche jamais et n'a donc jamais besoin de reconnaître
                    // ce noeud (patron du garde conditionnel côté toolbar,
                    // au-dessus, sur la même prop).
                    const extensions = [
                        TiptapStarterKit,
                        TiptapLink,
                        TiptapUnderline,
                        TiptapHighlight,
                        TiptapTextAlign.configure({ types: ['heading', 'paragraph'] }),
                    ];

                    if (config.forms) {
                        extensions.push(TiptapFormEmbed);
                    }

                    editor = new TiptapEditor({
                        element: root,
                        extensions,
                        content: config.initialValue || '',
                        onUpdate: () => {
                            hiddenInput.value = editor.getHTML();
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                            this.updateActive();
                        },
                        onSelectionUpdate: () => this.updateActive(),
                        onTransaction: () => this.updateActive(),
                    });

                    // L'`id`/`aria-describedby` posés en Blade sur le `<textarea>`
                    // caché (`x-ref="hiddenInput"`) sont invisibles pour les
                    // technologies d'assistance — un élément `display:none`
                    // n'entre jamais dans l'arbre d'accessibilité (même défaut que
                    // le jeton CSRF de la Pass 5.A, suivi n° 307/309). La vraie
                    // zone éditable est le noeud `contenteditable` que Tiptap
                    // monte dans `root` : `editor.view.dom`, seul élément que
                    // l'utilisateur clavier/lecteur d'écran atteint réellement.
                    if (config.hasError) {
                        editor.view.dom.setAttribute('aria-invalid', 'true');
                        editor.view.dom.setAttribute('aria-describedby', config.errorId);
                    }

                    this.updateActive();
                },

                // Texte brut inséré au curseur : le placeholder n'est pas un
                // noeud ProseMirror, seulement les caractères que
                // `PlaceholderRenderer` saura relire côté serveur. Rien de
                // « riche » ici, et c'est voulu — un noeud personnalisé
                // survivrait mal au copier-coller et à la version texte.
                insertVariable(name) {
                    if (! name) {
                        return;
                    }

                    // Les accolades sont assemblées en JavaScript pour que Blade
                    // n'en voie jamais la paire ouvrante, qu'il compilerait en
                    // `echo` PHP. L'échappement `@` de Blade a été essayé et
                    // écarté : il est fragile ici, un simple commentaire
                    // contenant la séquence ouvrante suffit à décaler la
                    // correspondance jusqu'au premier `}` doublé rencontré plus
                    // bas — défaut réel, trouvé en vérification navigateur.
                    const open = '{' + '{';
                    const close = '}' + '}';

                    editor.chain().focus().insertContent(open + ' ' + name + ' ' + close).run();
                },

                // Contrairement à `insertVariable()` ci-dessus, un vrai noeud
                // ProseMirror ici : rien n'impose de version texte de repli
                // pour du contenu de site (contrairement à un e-mail), et un
                // noeud atomique protège le marqueur d'une édition accidentelle
                // caractère par caractère (spec 14 §4, M8 point 6 Pass C4).
                insertForm(slug, label) {
                    if (! slug) {
                        return;
                    }

                    editor.chain().focus().insertContent({
                        type: 'formEmbed',
                        attrs: { slug, label: label || slug },
                    }).run();
                },

                updateActive() {
                    this.active = {
                        bold: editor.isActive('bold'),
                        italic: editor.isActive('italic'),
                        underline: editor.isActive('underline'),
                        strike: editor.isActive('strike'),
                        highlight: editor.isActive('highlight'),
                        h2: editor.isActive('heading', { level: 2 }),
                        h3: editor.isActive('heading', { level: 3 }),
                        h4: editor.isActive('heading', { level: 4 }),
                        bulletList: editor.isActive('bulletList'),
                        orderedList: editor.isActive('orderedList'),
                        blockquote: editor.isActive('blockquote'),
                        link: editor.isActive('link'),
                        alignLeft: editor.isActive({ textAlign: 'left' }),
                        alignCenter: editor.isActive({ textAlign: 'center' }),
                        alignRight: editor.isActive({ textAlign: 'right' }),
                        alignJustify: editor.isActive({ textAlign: 'justify' }),
                    };
                },

                toggleBold() {
                    editor.chain().focus().toggleBold().run();
                },

                toggleItalic() {
                    editor.chain().focus().toggleItalic().run();
                },

                toggleUnderline() {
                    editor.chain().focus().toggleUnderline().run();
                },

                toggleStrike() {
                    editor.chain().focus().toggleStrike().run();
                },

                toggleHighlight() {
                    editor.chain().focus().toggleHighlight().run();
                },

                toggleHeading(level) {
                    editor.chain().focus().toggleHeading({ level }).run();
                },

                setAlign(align) {
                    editor.chain().focus().setTextAlign(align).run();
                },

                toggleBulletList() {
                    editor.chain().focus().toggleBulletList().run();
                },

                toggleOrderedList() {
                    editor.chain().focus().toggleOrderedList().run();
                },

                toggleBlockquote() {
                    editor.chain().focus().toggleBlockquote().run();
                },

                setHorizontalRule() {
                    editor.chain().focus().setHorizontalRule().run();
                },

                clearFormat() {
                    editor.chain().focus().clearNodes().unsetAllMarks().run();
                },

                undo() {
                    editor.chain().focus().undo().run();
                },

                redo() {
                    editor.chain().focus().redo().run();
                },

                setLink() {
                    const previousUrl = editor.getAttributes('link').href;
                    const url = window.prompt(@js(__('baobab::admin.components.richtext_link_prompt')), previousUrl || '');

                    if (url === null) {
                        return;
                    }

                    if (url === '') {
                        editor.chain().focus().unsetLink().run();
                        return;
                    }

                    editor.chain().focus().setLink({ href: url }).run();
                },
            };
        }
    </script>
@endonce
