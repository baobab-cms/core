<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Admin\Sidebar\SidebarItem;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Blade;

it('redirects a guest away from /admin to the login screen', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('renders the admin layout shell for an authorized user', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
    ]);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $this->actingAs($user, 'baobab')
        ->get('/admin')
        ->assertOk()
        ->assertViewIs('baobab::admin.dashboard');
});

it('blocks a user without baobab.admin.access', function () {
    $user = User::create([
        'name' => 'Nobody',
        'email' => 'nobody@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user, 'baobab')
        ->get('/admin')
        ->assertForbidden();
});

it('renders pushed content into each of the five named admin stacks', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin2@example.com',
        'password' => 'secret',
    ]);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    $this->actingAs($user, 'baobab');

    $template = <<<'BLADE'
    @extends('baobab::layouts.admin')
    @push('admin.head') MARKER-HEAD @endpush
    @push('admin.topbar.before-user') MARKER-TOPBAR @endpush
    @push('admin.sidebar.footer') MARKER-SIDEBAR-FOOTER @endpush
    @push('admin.content.before') MARKER-CONTENT-BEFORE @endpush
    @push('admin.scripts') MARKER-SCRIPTS @endpush
    @section('content') MARKER-CONTENT @endsection
    BLADE;

    $html = Blade::render($template);

    expect($html)
        ->toContain('MARKER-HEAD')
        ->toContain('MARKER-TOPBAR')
        ->toContain('MARKER-SIDEBAR-FOOTER')
        ->toContain('MARKER-CONTENT-BEFORE')
        ->toContain('MARKER-SCRIPTS')
        ->toContain('MARKER-CONTENT');
});

// ── Pass 5.D (suivi n° 307 constats 6-8) : omnibox + sidebar ───────────────────

it('exposes ARIA combobox/listbox semantics on the omnibox', function () {
    $user = User::create(['name' => 'Admin', 'email' => 'admin-omnibox@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');

    $html = $this->actingAs($user, 'baobab')->get('/admin')->getContent();

    expect((string) $html)
        ->toContain('role="combobox"')
        ->toContain('aria-autocomplete="list"')
        ->toContain('aria-controls="omnibox-listbox"')
        ->toContain(':aria-expanded="groups.length > 0 ? \'true\' : \'false\'"')
        ->toContain(':aria-activedescendant="activeDescendant()"')
        ->toContain('id="omnibox-listbox"')
        ->toContain('role="listbox"')
        ->toContain('role="option"');
});

it('labels the admin sidebar nav landmark distinctly from other <nav> regions', function () {
    $html = Blade::render("@include('baobab::layouts.partials.admin-sidebar')");

    expect($html)->toContain('aria-label="'.__('baobab::admin.sidebar.nav_label').'"');
});

it('exposes aria-expanded on a collapsible sidebar group and aria-current on the active link', function () {
    $items = [
        new SidebarItem(
            id: 1,
            label: 'Contenu',
            icon: 'bi-app-indicator',
            url: null,
            order: 0,
            children: [
                new SidebarItem(id: 2, label: 'Articles', icon: 'bi-app-indicator', url: '/admin/articles', order: 0, isActive: true),
            ],
        ),
    ];

    $html = Blade::render("@include('baobab::layouts.partials.admin-sidebar-items', ['items' => \$items])", ['items' => $items]);

    expect($html)
        ->toContain('x-bind:aria-expanded="open ? \'true\' : \'false\'"')
        ->toContain('aria-current="page"');
});

it('omits aria-current from a sidebar link that is not the current page', function () {
    $items = [new SidebarItem(id: 1, label: 'Médias', icon: 'bi-images', url: '/admin/media', order: 0)];

    $html = Blade::render("@include('baobab::layouts.partials.admin-sidebar-items', ['items' => \$items])", ['items' => $items]);

    expect($html)->not->toContain('aria-current');
});
