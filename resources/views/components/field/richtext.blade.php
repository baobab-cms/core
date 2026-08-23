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
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-foreground">{{ $label }}</label>
    @endif

    <div
        x-data="richTextEditor({ initialValue: @js(old($name, $value)) })"
        x-init="mount($refs.editorRoot, $refs.hiddenInput)"
        @class([
            'overflow-hidden rounded-md border',
            'border-danger' => $errors->has($name),
            'border-border' => ! $errors->has($name),
        ])
    >
        <div class="flex flex-wrap items-center gap-1 border-b border-border bg-surface-subtle px-2 py-1">
            <button type="button" x-on:click="toggleBold()" x-bind:class="{ 'bg-surface': active.bold }" class="rounded px-2 py-1 text-xs font-semibold hover:bg-surface">B</button>
            <button type="button" x-on:click="toggleItalic()" x-bind:class="{ 'bg-surface': active.italic }" class="rounded px-2 py-1 text-xs italic hover:bg-surface">I</button>
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
            <button type="button" x-on:click="toggleBulletList()" x-bind:class="{ 'bg-surface': active.bulletList }" class="rounded px-2 py-1 text-xs hover:bg-surface">
                {{ __('baobab::admin.components.richtext_bullet_list') }}
            </button>
            <button type="button" x-on:click="toggleOrderedList()" x-bind:class="{ 'bg-surface': active.orderedList }" class="rounded px-2 py-1 text-xs hover:bg-surface">
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
        </div>

        <div x-ref="editorRoot" class="tiptap px-3 py-2 text-sm text-foreground" data-tiptap-editor></div>

        <textarea id="{{ $name }}" name="{{ $name }}" x-ref="hiddenInput" class="hidden" {{ $attributes }}>{{ old($name, $value) }}</textarea>
    </div>

    @error($name)
        <p class="mt-1 text-xs text-danger">{{ $message }}</p>
    @enderror
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
                    editor = new TiptapEditor({
                        element: root,
                        extensions: [
                            TiptapStarterKit,
                            TiptapLink,
                            TiptapUnderline,
                            TiptapHighlight,
                            TiptapTextAlign.configure({ types: ['heading', 'paragraph'] }),
                        ],
                        content: config.initialValue || '',
                        onUpdate: () => {
                            hiddenInput.value = editor.getHTML();
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                            this.updateActive();
                        },
                        onSelectionUpdate: () => this.updateActive(),
                        onTransaction: () => this.updateActive(),
                    });

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
