<?php

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

it('renders x-baobab::modal scaffold targeting the given name', function () {
    $html = Blade::render('<x-baobab::modal name="delete-user">Content</x-baobab::modal>');

    expect($html)
        ->toContain("\$event.detail === 'delete-user'")
        ->toContain('Content')
        ->toContain('x-cloak');
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
        ->toContain('Delete role');
});

it('renders x-baobab::confirm without a retype input when expectedText is omitted', function () {
    $html = Blade::render('<x-baobab::confirm name="logout" title="Log out"></x-baobab::confirm>');

    expect($html)->not->toContain('typed !==');
});

it('renders a flashed session toast', function () {
    session()->flash('toast', ['type' => 'success', 'message' => 'Saved']);

    $html = Blade::render('<x-baobab::toasts />');

    expect($html)->toContain('Saved');
});

/**
 * Position et persistance, demandées en vérification du chantier 2 (n° 205) :
 * en bas de page, un message long chevauchait le pied de la sidebar, et cinq
 * secondes ne suffisent pas à lire une erreur qui dit quoi corriger.
 */
it('renders toasts at the top, and keeps the ones that require an action', function () {
    $html = Blade::render('<x-baobab::toasts />');

    expect($html)
        ->toContain('top-4')
        ->not->toContain('bottom-4')
        // Le délai d'effacement ne s'applique qu'aux types qui n'appellent
        // aucune correction.
        ->toContain("type !== 'danger'")
        ->toContain("type !== 'warning'");
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

    expect($html)->toContain('Invalid.')->toContain('border-danger');
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
