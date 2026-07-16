<?php

use Baobab\Users\Models\User;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

it('queues a test notification to a user resolved by email', function () {
    Queue::fake();
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.configurable', 'channels' => ['database'], 'configurable' => true],
    ]]);

    $user = User::create(['name' => 'CLI Target', 'email' => 'cli-target@example.com', 'password' => 'secret']);

    $exitCode = Artisan::call('baobab:notify:test', ['key' => 'core.configurable', 'user' => 'cli-target@example.com']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('cli-target@example.com');

    Queue::assertPushedOn('baobab', SendQueuedNotifications::class);
});

it('resolves a user by numeric id', function () {
    Queue::fake();
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.configurable', 'channels' => ['database'], 'configurable' => true],
    ]]);

    $user = User::create(['name' => 'CLI Target', 'email' => 'cli-target-id@example.com', 'password' => 'secret']);

    $exitCode = Artisan::call('baobab:notify:test', ['key' => 'core.configurable', 'user' => (string) $user->id]);

    expect($exitCode)->toBe(0);

    Queue::assertPushedOn('baobab', SendQueuedNotifications::class);
});

it('fails with a clear message when the user does not exist', function () {
    $exitCode = Artisan::call('baobab:notify:test', ['key' => 'core.test', 'user' => 'nobody@example.com']);
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('introuvable');
});
