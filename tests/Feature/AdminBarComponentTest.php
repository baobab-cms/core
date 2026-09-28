<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Modules\Models\Module;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Blade;

afterEach(function () {
    removeThemeLink('acme-theme');
});

it('renders nothing for a guest', function () {
    $html = Blade::render('<x-baobab::admin-bar />');

    expect(trim($html))->toBe('');
});

it('renders nothing for an authenticated user without admin access', function () {
    $user = User::create(['name' => 'No Access', 'email' => 'no-access@example.com', 'password' => 'secret']);
    $this->actingAs($user, 'baobab');

    $html = Blade::render('<x-baobab::admin-bar />');

    expect(trim($html))->toBe('');
});

it('renders the bar with a dashboard link and the actor name for an admin', function () {
    $user = User::create(['name' => 'Site Admin', 'email' => 'site-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    $this->actingAs($user, 'baobab');

    $html = Blade::render('<x-baobab::admin-bar />');

    expect($html)->toContain('Site Admin')
        ->and($html)->toContain(route('admin.dashboard'))
        ->and($html)->toContain(route('logout'));
});

// ── M9 point 5, Pass A (Coquille) — indicateur de préview, suivi n° 366 ─────

it('says nothing about a preview when no theme preview session is running', function () {
    $user = User::create(['name' => 'Site Admin', 'email' => 'no-preview@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    $this->actingAs($user, 'baobab');

    $html = Blade::render('<x-baobab::admin-bar />');

    expect($html)->not->toContain(route('baobab.theme-preview.stop'));
});

it('shows the previewed theme title and an exit link during a theme preview session', function () {
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/theme');
    $theme = Module::where('name', 'acme/theme')->firstOrFail();

    $user = User::create(['name' => 'Site Admin', 'email' => 'previewing@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($user, 'baobab.admin.access');
    $this->actingAs($user, 'baobab');
    session(['baobab.preview_theme_id' => $theme->id]);

    $html = Blade::render('<x-baobab::admin-bar />');

    expect($html)->toContain($theme->title)
        ->and($html)->toContain(route('baobab.theme-preview.stop'));
});
