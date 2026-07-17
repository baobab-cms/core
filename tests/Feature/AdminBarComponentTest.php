<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Blade;

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
