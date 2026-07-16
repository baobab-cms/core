<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Actions\Modules\ActivateModule;
use Baobab\Actions\Modules\InstallModule;
use Baobab\Facades\Hook;
use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Notify\Notifier;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function notifyActor(): User
{
    static $counter = 0;
    $counter++;

    $user = User::create([
        'name' => "Notify Actor {$counter}",
        'email' => "notify-actor-{$counter}@example.com",
        'password' => 'secret',
    ]);

    app(GrantPermission::class)($user, 'baobab.admin.access');

    return $user;
}

function installActivePendingNotification(): void
{
    config(['baobab.modules.paths' => ['local' => [fixtureModulesPath('local/*')]]]);
    app(InstallModule::class)('acme/notifier');
    app(ActivateModule::class)('acme/notifier');
}

it('dispatches both the database and mail channels for a configurable notification', function () {
    installActivePendingNotification();
    Notification::fake();
    Queue::fake();

    $recipient = notifyActor();

    app(Notifier::class)->send('acme.notifier.pending', [$recipient], ['item' => ['name' => 'Voiture']]);

    Notification::assertSentTo($recipient, BaobabNotification::class);
    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === $recipient->email);
});

it('honors a disabled channel preference', function () {
    installActivePendingNotification();
    Notification::fake();
    Queue::fake();

    $recipient = notifyActor();
    NotificationPreference::create([
        'user_id' => $recipient->id,
        'key' => 'acme.notifier.pending',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    app(Notifier::class)->send('acme.notifier.pending', [$recipient], ['item' => ['name' => 'Voiture']]);

    Notification::assertSentTo($recipient, BaobabNotification::class);
    Queue::assertNotPushed(SendQueuedMail::class);
});

it('ignores preferences for a non-configurable notification', function () {
    config(['baobab.notifications.declarations' => [
        ['key' => 'core.locked', 'channels' => ['database'], 'configurable' => false],
    ]]);
    Notification::fake();

    $recipient = notifyActor();
    NotificationPreference::create([
        'user_id' => $recipient->id,
        'key' => 'core.locked',
        'channel' => 'database',
        'enabled' => false,
    ]);

    app(Notifier::class)->send('core.locked', [$recipient]);

    Notification::assertSentTo($recipient, BaobabNotification::class);
});

it('cancels the send when the baobab.notification.sending filter returns null', function () {
    installActivePendingNotification();
    Notification::fake();
    Queue::fake();

    Hook::modify('baobab.notification.sending', fn () => null, priority: 5);

    $recipient = notifyActor();
    app(Notifier::class)->send('acme.notifier.pending', [$recipient], ['item' => ['name' => 'Voiture']]);

    Notification::assertNothingSent();
    Queue::assertNotPushed(SendQueuedMail::class);
});

it('fires baobab.notification.sent for each recipient', function () {
    installActivePendingNotification();
    Notification::fake();
    Queue::fake();

    $captured = [];
    Hook::listen('baobab.notification.sent', function (string $key, User $recipient) use (&$captured): void {
        $captured[] = [$key, $recipient->id];
    });

    $recipient = notifyActor();
    app(Notifier::class)->send('acme.notifier.pending', [$recipient], ['item' => ['name' => 'Voiture']]);

    expect($captured)->toBe([['acme.notifier.pending', $recipient->id]]);
});
