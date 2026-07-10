<?php

use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Artisan;

// ── baobab:super-admin ────────────────────────────────────────────────────────

it('creates a new user and assigns super-admin role', function () {
    $exitCode = Artisan::call('baobab:super-admin', ['email' => 'admin@example.com']);

    expect($exitCode)->toBe(0);

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->hasRole('super-admin', 'baobab'))->toBeTrue();
});

it('assigns super-admin role to an existing user without creating a duplicate', function () {
    User::create([
        'name' => 'Existing',
        'email' => 'existing@example.com',
        'password' => 'password',
    ]);

    Artisan::call('baobab:super-admin', ['email' => 'existing@example.com']);
    Artisan::call('baobab:super-admin', ['email' => 'existing@example.com']);

    $user = User::where('email', 'existing@example.com')->firstOrFail();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1)
        ->and($user->hasRole('super-admin', 'baobab'))->toBeTrue();
});

// ── guard `baobab` ────────────────────────────────────────────────────────────

it('registers the baobab guard in the auth config', function () {
    $guards = config('auth.guards');

    expect($guards)->toHaveKey('baobab')
        ->and($guards['baobab']['driver'])->toBe('session')
        ->and($guards['baobab']['provider'])->toBe('baobab_users');
});
