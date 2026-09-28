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

// ── M9 point 5, Pass A (Coquille) — icône + nom en sidebar, suivi n° 366 ────

it('pairs the app name with its initial-letter badge when no branding favicon is set', function () {
    $html = Blade::render("@include('baobab::layouts.partials.admin-sidebar')");

    expect($html)->toContain(config('app.name', 'Baobab'))
        ->and($html)->toContain(mb_substr(config('app.name', 'Baobab'), 0, 1))
        ->and($html)->toContain(route('admin.dashboard'));
});

it('no longer carries the branding logo in the topbar, which moved to the sidebar', function () {
    $html = Blade::render("@include('baobab::layouts.partials.admin-topbar')");

    expect($html)->not->toContain(route('admin.dashboard'));
});

// ── M9 point 5, Pass A (Coquille) — menu raccourcis, suivi n° 366 ───────────

it('shows no quick actions trigger for a guest', function () {
    $html = Blade::render("@include('baobab::layouts.partials.admin-topbar')");

    expect($html)->not->toContain(__('baobab::admin.quick_actions.title'));
});

it('shows only the quick actions the user holds the permission for', function () {
    $user = User::create(['name' => 'Media Only', 'email' => 'media-only@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    app(GrantPermission::class)($user, 'baobab.media.view');

    $html = $this->actingAs($user, 'baobab')->get('/admin')->getContent();

    expect((string) $html)
        ->toContain(__('baobab::admin.quick_actions.title'))
        ->toContain(route('admin.media.index'))
        ->not->toContain(route('admin.themes.index'))
        ->not->toContain(route('admin.menus.index'));
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
