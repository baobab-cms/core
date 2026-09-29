<?php

use Baobab\Support\PublishBrandAssets;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

it('renders x-baobab::page with title, breadcrumbs and slot', function () {
    $html = Blade::render(<<<'BLADE'
    <x-baobab::page title="Users" :breadcrumbs="[['Home', '/admin'], ['Users']]">
        <p>Body content</p>
    </x-baobab::page>
    BLADE);

    expect($html)
        ->toContain('Users')
        ->toContain('Home')
        ->toContain('/admin')
        ->toContain('Body content');
});

it('renders the x-baobab::page actions slot', function () {
    $html = Blade::render(<<<'BLADE'
    <x-baobab::page title="Users">
        <x-slot:actions><span>Create user</span></x-slot:actions>
        Body
    </x-baobab::page>
    BLADE);

    expect($html)->toContain('Create user');
});

// ── M9 point 5, Pass A (Coquille) — suivi n° 366 ────────────────────────────

it('renders x-baobab::avatar with the first two initials of a full name', function () {
    $html = Blade::render('<x-baobab::avatar name="Jean Dupont" />');

    expect($html)->toContain('JD')->not->toContain('Jean Dupont');
});

it('renders x-baobab::avatar with a single initial for a one-word name', function () {
    $html = Blade::render('<x-baobab::avatar name="Madonna" />');

    expect(trim($html))->toContain('>M<')->not->toContain('Madonna');
});

it('renders the x-baobab::page mono subtitle only when given one', function () {
    $withSubtitle = Blade::render(<<<'BLADE'
    <x-baobab::page title="Cars" subtitle="ct_cars">
        Body
    </x-baobab::page>
    BLADE);

    $withoutSubtitle = Blade::render(<<<'BLADE'
    <x-baobab::page title="Cars">
        Body
    </x-baobab::page>
    BLADE);

    expect($withSubtitle)->toContain('font-mono')->toContain('ct_cars')
        ->and($withoutSubtitle)->not->toContain('font-mono');
});

it('renders x-baobab::card with header, footer and slot', function () {
    $html = Blade::render(<<<'BLADE'
    <x-baobab::card>
        <x-slot:header>Header</x-slot:header>
        Body
        <x-slot:footer>Footer</x-slot:footer>
    </x-baobab::card>
    BLADE);

    expect($html)->toContain('Header')->toContain('Body')->toContain('Footer');
});

it('renders x-baobab::button variants and href mode', function () {
    $danger = Blade::render('<x-baobab::button variant="danger">Delete</x-baobab::button>');
    expect($danger)->toContain('bg-danger')->toContain('Delete');

    $link = Blade::render('<x-baobab::button href="/foo">Go</x-baobab::button>');
    expect($link)->toContain('<a')->toContain('href="/foo"');
});

it('renders the primary button on the AA-safe derived fill and the on-primary token, not raw primary/white', function () {
    $html = Blade::render('<x-baobab::button>Save</x-baobab::button>');

    expect($html)->toContain('bg-primary-strong')
        ->toContain('text-on-primary')
        ->not->toContain('bg-primary ')
        ->not->toContain('text-white');
});

it('renders x-baobab::button size variants, defaulting to default', function () {
    $default = Blade::render('<x-baobab::button>Save</x-baobab::button>');
    expect($default)->toContain('px-3 py-2');

    $small = Blade::render('<x-baobab::button size="sm">Save</x-baobab::button>');
    expect($small)->toContain('px-2 py-1.5');
});

it('gives x-baobab::button a visible focus ring', function () {
    $html = Blade::render('<x-baobab::button>Save</x-baobab::button>');

    expect($html)->toContain('focus-visible:ring-2')->toContain('focus-visible:ring-primary');
});

it('renders x-baobab::badge variants', function () {
    $html = Blade::render('<x-baobab::badge variant="success">Active</x-baobab::badge>');
    expect($html)->toContain('bg-leaf-50')->toContain('text-leaf-600')->toContain('Active');
});

it('renders x-baobab::empty-state with a default and a custom message', function () {
    $default = Blade::render('<x-baobab::empty-state />');
    expect($default)->toContain('Aucun résultat.');

    $custom = Blade::render('<x-baobab::empty-state message="Rien ici" />');
    expect($custom)->toContain('Rien ici');
});

it('renders x-baobab::table headers, sortable links and rows', function () {
    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" />',
        [
            'columns' => [
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                ['key' => 'email', 'label' => 'Email'],
            ],
            'rows' => collect([
                ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ]),
        ]
    );

    expect($html)
        ->toContain('Alice')
        ->toContain('alice@example.com')
        ->toContain('sort=name');
});

it('renders the empty state when the table has no rows', function () {
    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" />',
        ['columns' => [], 'rows' => collect()]
    );

    expect($html)->toContain('Aucun résultat.');
});

it('renders bulk action checkboxes and buttons when bulkActions are provided', function () {
    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" :bulk-actions="$bulkActions" />',
        [
            'columns' => [['key' => 'name', 'label' => 'Name']],
            'rows' => collect([['id' => 1, 'name' => 'Alice']]),
            'bulkActions' => [['label' => 'Delete', 'route' => '/admin/bulk-delete']],
        ]
    );

    expect($html)
        ->toContain('name="ids[]"')
        ->toContain('Delete')
        ->toContain('/admin/bulk-delete');
});

it('gives x-baobab::table a header without forced uppercase, and a row hover transition', function () {
    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" />',
        [
            'columns' => [['key' => 'name', 'label' => 'Name']],
            'rows' => collect([['id' => 1, 'name' => 'Alice']]),
        ]
    );

    expect($html)
        ->not->toContain('uppercase')
        ->toContain('hover:bg-surface');
});

it('applies primary, mono and numeric column variants (suivi n° 371)', function () {
    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" />',
        [
            'columns' => [
                ['key' => 'name', 'label' => 'Name', 'variant' => 'primary'],
                ['key' => 'slug', 'label' => 'Slug', 'variant' => 'mono'],
                ['key' => 'count', 'label' => 'Count', 'variant' => 'numeric'],
            ],
            'rows' => collect([['id' => 1, 'name' => 'Alice', 'slug' => 'alice', 'count' => 3]]),
        ]
    );

    expect($html)
        ->toContain('text-foreground font-medium')
        ->toContain('font-mono text-muted')
        ->toContain('text-right tabular-nums');
});

it('leaves untagged columns on their current, un-varianted styling', function () {
    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" />',
        [
            'columns' => [['key' => 'name', 'label' => 'Name']],
            'rows' => collect([['id' => 1, 'name' => 'Alice']]),
        ]
    );

    expect($html)->not->toContain('tabular-nums')->not->toContain('font-mono');
});

it('shows an explicit result total in the footer, next to pagination, for a length-aware paginator', function () {
    $paginator = new LengthAwarePaginator(
        items: [['id' => 1, 'name' => 'Alice']],
        total: 47,
        perPage: 15,
        currentPage: 1,
    );

    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" />',
        ['columns' => [['key' => 'name', 'label' => 'Name']], 'rows' => $paginator]
    );

    expect($html)->toContain('47 résultats');
});

it('lets a screen customize the result label instead of the generic default', function () {
    $paginator = new LengthAwarePaginator(
        items: [['id' => 1, 'name' => 'Alice']],
        total: 3,
        perPage: 15,
        currentPage: 1,
    );

    $html = Blade::render(
        '<x-baobab::table :columns="$columns" :rows="$rows" result-label="{1} :count contenu|[2,*] :count contenus" />',
        ['columns' => [['key' => 'name', 'label' => 'Name']], 'rows' => $paginator]
    );

    expect($html)->toContain('3 contenus')->not->toContain('3 résultats');
});

it('renders x-baobab::modal scaffold targeting the given name', function () {
    $html = Blade::render('<x-baobab::modal name="delete-user">Content</x-baobab::modal>');

    expect($html)
        ->toContain("\$event.detail === 'delete-user'")
        ->toContain('Content')
        ->toContain('x-cloak');
});

it('gives x-baobab::modal a dialog role, a focus trap and an aria-label fallback', function () {
    $html = Blade::render('<x-baobab::modal name="pick-media" aria-label="Media library">Content</x-baobab::modal>');

    expect($html)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-label="Media library"')
        ->toContain('x-ref="panel"')
        ->toContain('trapFocus($event)')
        ->toContain('function baobabModal(');
});

it('lets x-baobab::modal be labelled and described by ids instead of a plain aria-label', function () {
    $html = Blade::render('<x-baobab::modal name="delete-role" aria-labelledby="delete-role-title" aria-describedby="delete-role-description">Content</x-baobab::modal>');

    expect($html)
        ->toContain('aria-labelledby="delete-role-title"')
        ->toContain('aria-describedby="delete-role-description"')
        ->not->toContain('aria-label="'); // no aria-label fallback once aria-labelledby is given
});

it('renders x-baobab::confirm with a retype gate when expectedText is set', function () {
    $html = Blade::render(<<<'BLADE'
    <x-baobab::confirm name="delete-role" title="Delete role" expected-text="acme-role">
        <form method="POST" action="/admin/roles/1"></form>
    </x-baobab::confirm>
    BLADE);

    expect($html)
        ->toContain('acme-role')
        ->toContain('typed !==')
        ->toContain('Delete role')
        // the title is exposed to the modal's aria-labelledby, not just displayed
        ->toContain('id="delete-role-title"')
        ->toContain('aria-labelledby="delete-role-title"')
        // the dangerous action is disabled at the HTML level (fieldset), not just visually
        ->toContain('<fieldset')
        ->toContain("x-bind:disabled=\"typed !== 'acme-role'\"")
        // the confirmation input has a real accessible name
        ->toContain('for="delete-role-confirm-text"')
        ->toContain('id="delete-role-confirm-text"');
});

it('renders x-baobab::confirm without a retype input when expectedText is omitted', function () {
    $html = Blade::render('<x-baobab::confirm name="logout" title="Log out"></x-baobab::confirm>');

    expect($html)
        ->not->toContain('typed !==')
        ->not->toContain('x-bind:disabled');
});

it('links x-baobab::confirm\'s description to the modal via aria-describedby', function () {
    $html = Blade::render(<<<'BLADE'
    <x-baobab::confirm name="disable-2fa" title="Disable 2FA">
        <x-slot:description>This will remove two-factor protection.</x-slot:description>
        <form method="POST" action="/admin/account/security/disable"></form>
    </x-baobab::confirm>
    BLADE);

    expect($html)
        ->toContain('id="disable-2fa-description"')
        ->toContain('aria-describedby="disable-2fa-description"');
});

it('renders a flashed session toast', function () {
    session()->flash('toast', ['type' => 'success', 'message' => 'Saved']);

    $html = Blade::render('<x-baobab::toasts />');

    expect($html)->toContain('Saved');
});

it('gives each x-baobab::toasts item a live region role and a keyboard-focusable close button', function () {
    $html = Blade::render('<x-baobab::toasts />');

    expect($html)
        // suivi n° 307 constat 4 : alerte assertive pour ce qui ne s'efface jamais
        // seul (danger/warning), statut poli pour ce qui disparaît de soi-même.
        ->toContain("toast.type === 'danger' || toast.type === 'warning' ? 'alert' : 'status'")
        ->toContain("toast.type === 'danger' || toast.type === 'warning' ? 'assertive' : 'polite'")
        // un vrai bouton, pas un <div x-on:click> — focusable et activable au clavier.
        ->toContain('<button')
        ->toContain('aria-label="Fermer"')
        ->toContain('dismiss(toast.id)');
});

/**
 * Position et persistance, demandées en vérification du chantier 2 (n° 205) :
 * en bas de page, un message long chevauchait le pied de la sidebar ; en
 * `fixed` tout en haut, il masquait le bandeau d'environnement. Et cinq
 * secondes ne suffisent pas à lire une erreur qui dit quoi corriger.
 */
it('renders toasts in the flow, and keeps the ones that require an action', function () {
    $html = Blade::render('<x-baobab::toasts />');

    expect($html)
        // Hors flux, le toast recouvrait forcément quelque chose — un bandeau
        // système en l'occurrence.
        ->not->toContain('fixed')
        ->not->toContain('bottom-4')
        // Le délai d'effacement ne s'applique qu'aux types qui n'appellent
        // aucune correction.
        ->toContain("type !== 'danger'")
        ->toContain("type !== 'warning'");
});

/**
 * Le layout place les toasts **après** les bandeaux système, et une seule
 * fois : en `fixed` au bas du document, ils passaient par-dessus tout.
 */
it('places the toasts after the system banners in the admin layout', function () {
    $layout = file_get_contents(__DIR__.'/../../resources/views/layouts/admin.blade.php');

    expect(substr_count((string) $layout, '<x-baobab::toasts />'))->toBe(1)
        ->and(strpos((string) $layout, 'staging-noindex-banner'))
        ->toBeLessThan((int) strpos((string) $layout, '<x-baobab::toasts />'));
});

it('renders x-baobab::form with csrf and method spoofing', function () {
    $html = Blade::render('<x-baobab::form method="PUT" action="/admin/roles/1"></x-baobab::form>');

    expect($html)
        ->toContain('method="POST"')
        ->toContain('name="_token"')
        ->toContain('name="_method"')
        ->toContain('value="PUT"');
});

it('renders x-baobab::field.text with label and old value', function () {
    $html = Blade::render('<x-baobab::field.text name="email" label="E-mail" value="a@b.com" />');

    expect($html)
        ->toContain('E-mail')
        ->toContain('name="email"')
        ->toContain('value="a@b.com"');
});

it('renders a validation error under x-baobab::field.text', function () {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['email' => 'Invalid.']));
    view()->share('errors', $errors);

    $html = Blade::render('<x-baobab::field.text name="email" label="E-mail" />');

    expect($html)
        ->toContain('Invalid.')
        ->toContain('border-danger')
        // suivi n° 307 constat 3 / n° 311 : l'erreur doit être reliée au champ pour les
        // technologies d'assistance, pas seulement visible à l'écran.
        ->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="email-error"')
        ->toContain('id="email-error"');
});

it('marks x-baobab::field.text valid and without aria-describedby when there is no error', function () {
    $html = Blade::render('<x-baobab::field.text name="email" label="E-mail" />');

    expect($html)
        ->toContain('aria-invalid="false"')
        ->not->toContain('aria-describedby')
        ->not->toContain('email-error');
});

it('repeats aria-invalid/aria-describedby on every option of x-baobab::field.radio', function () {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['plan' => 'Choose a plan.']));
    view()->share('errors', $errors);

    $html = Blade::render(
        '<x-baobab::field.radio name="plan" label="Plan" :options="$options" />',
        ['options' => ['free' => 'Free', 'pro' => 'Pro']]
    );

    expect(substr_count($html, 'aria-describedby="plan-error"'))->toBe(2)
        ->and(substr_count($html, 'aria-invalid="true"'))->toBe(2)
        ->and($html)->toContain('id="plan-error"');
});

it('puts aria-invalid/aria-describedby on the trigger button of x-baobab::field.media and field.gallery, not the hidden input', function () {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['cover' => 'Required.']));
    view()->share('errors', $errors);

    $media = Blade::render('<x-baobab::field.media name="cover" label="Cover" />');

    expect($media)
        ->toContain('<input type="hidden" name="cover"')
        ->toContain('aria-describedby="cover-error"');

    // Un aria-describedby porté par le <input type="hidden"> serait invisible pour les
    // technologies d'assistance (display:none, hors de l'arbre d'accessibilité) — même
    // défaut que le jeton CSRF corrigé en Pass 5.A (suivi n° 309) : vérifier qu'il est
    // bien sur le bouton, pas sur l'input cache lui-même.
    $hiddenInputLine = collect(explode("\n", $media))->first(fn ($line) => str_contains($line, 'type="hidden" name="cover"'));
    expect($hiddenInputLine)->not->toContain('aria-describedby');

    $galleryErrors = new ViewErrorBag;
    $galleryErrors->put('default', new MessageBag(['photos' => 'Required.']));
    view()->share('errors', $galleryErrors);

    $gallery = Blade::render('<x-baobab::field.gallery name="photos" label="Photos" />');

    expect($gallery)->toContain('aria-describedby="photos-error"');
});

it('exposes the media picker selection state and a loading indicator via ARIA', function () {
    $html = Blade::render('<x-baobab::field.media name="cover" label="Cover" />');

    // suivi n° 307 constat 9 : la vignette sélectionnée ne portait qu'une classe
    // visuelle (ring-2), aucun état exposé aux technologies d'assistance.
    expect($html)
        ->toContain("x-bind:aria-pressed=\"isSelected(item.id) ? 'true' : 'false'\"")
        // le chargement asynchrone n'avait ni texte, ni aria-busy.
        ->toContain("x-bind:aria-busy=\"loading ? 'true' : 'false'\"")
        ->toContain('x-if="loading"')
        ->toContain(__('baobab::admin.components.loading'));
});

it('passes hasError/errorId to the richtext editor config when there is an error', function () {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['body' => 'Required.']));
    view()->share('errors', $errors);

    $html = Blade::render('<x-baobab::field.richtext name="body" label="Body" />');

    expect($html)
        ->toContain('hasError: true')
        ->toContain("errorId: 'body-error'")
        ->toContain('id="body-error"');
});

it('renders x-baobab::field.textarea, field.select and field.checkbox', function () {
    $textarea = Blade::render('<x-baobab::field.textarea name="bio" label="Bio" value="Hello" />');
    expect($textarea)->toContain('Bio')->toContain('Hello');

    $select = Blade::render(
        '<x-baobab::field.select name="role" label="Role" :options="$options" value="editor" />',
        ['options' => ['admin' => 'Admin', 'editor' => 'Editor']]
    );
    expect($select)->toContain('Role')->toContain('value="editor" selected');

    $checkbox = Blade::render('<x-baobab::field.checkbox name="active" label="Active" :checked="true" />');
    expect($checkbox)->toContain('Active')->toContain('checked');
});

it('gives text, textarea, select and checkbox fields a visible focus ring and a surface background', function () {
    $text = Blade::render('<x-baobab::field.text name="email" label="E-mail" />');
    $textarea = Blade::render('<x-baobab::field.textarea name="bio" label="Bio" />');
    $select = Blade::render('<x-baobab::field.select name="role" label="Role" :options="$options" />', ['options' => ['admin' => 'Admin']]);
    $checkbox = Blade::render('<x-baobab::field.checkbox name="active" label="Active" />');

    foreach ([$text, $textarea, $select, $checkbox] as $html) {
        expect($html)->toContain('bg-surface')->toContain('focus:ring-2')->toContain('focus:ring-primary');
    }
});

it('renders x-baobab::field.slug in mono, delegating to field.text', function () {
    $html = Blade::render('<x-baobab::field.slug name="slug" label="Slug" value="mon-titre" />');

    expect($html)
        ->toContain('font-mono')
        ->toContain('placeholder="mon-titre-de-page"')
        ->toContain('value="mon-titre"');
});

// ── M9 point 5, Pass D (Écrans d'authentification) — suivi n° 373 ──────────

it('renders x-baobab::brand-mark with a publish-baobab/images src and a Baobab alt text', function () {
    $html = Blade::render('<x-baobab::brand-mark class="h-14 w-14" />');

    expect($html)
        ->toContain('<img')
        ->toContain('src="/baobab/images/baobab-icon.png?v=')
        ->toContain('alt="Baobab"')
        ->toContain('h-14 w-14');

    expect(is_file(public_path('baobab/images/baobab-icon.png')))->toBeTrue();
});

it('publishes the same brand icon url on repeated calls (idempotent symlink)', function () {
    $first = app(PublishBrandAssets::class)();
    $second = app(PublishBrandAssets::class)();

    expect($first)->toBe($second);
});
