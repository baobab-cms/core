<?php

use Baobab\Access\Actions\GrantPermission;
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
