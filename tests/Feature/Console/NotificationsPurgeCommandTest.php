<?php

use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

function purgeCommandActor(): User
{
    static $counter = 0;
    $counter++;

    return User::create([
        'name' => "Purge Command Actor {$counter}",
        'email' => "purge-command-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);
}

it('purges notifications older than the configured retention, keeping recent ones', function () {
    config(['baobab.notifications.retention_days' => 90]);

    $user = purgeCommandActor();

    Notification::sendNow($user, new BaobabNotification('core.test', 'Ancienne', []));
    $old = $user->notifications()->sole();
    $old->forceFill(['created_at' => now()->subDays(91)])->save();

    Notification::sendNow($user, new BaobabNotification('core.test', 'Récente', []));

    Artisan::call('notifications:purge-old');

    expect($user->notifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['description'])->toBe('Récente');
});

it('purges regardless of read status', function () {
    config(['baobab.notifications.retention_days' => 90]);

    $user = purgeCommandActor();

    Notification::sendNow($user, new BaobabNotification('core.test', 'Ancienne lue', []));
    $old = $user->notifications()->sole();
    $old->markAsRead();
    $old->forceFill(['created_at' => now()->subDays(91)])->save();

    Artisan::call('notifications:purge-old');

    expect($user->notifications()->count())->toBe(0);
});
