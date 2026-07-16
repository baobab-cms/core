<?php

use Baobab\Mail\Jobs\SendQueuedMail;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Notify\Notifications\BaobabNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('notifies the target user when an admin starts impersonating them', function () {
    Notification::fake();
    Queue::fake();

    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    $this->actingAs($actor, 'baobab')
        ->post("/admin/users/{$target->id}/impersonate")
        ->assertRedirect(route('admin.dashboard'));

    Notification::assertSentTo(
        $target,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($target)['key'] === 'core.security.impersonation_started',
    );
    Notification::assertNotSentTo($actor, BaobabNotification::class);
    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === $target->email);
});

it('notifies the target even with a disabled preference, since the notification is not configurable', function () {
    Notification::fake();
    Queue::fake();

    $actor = impersonationActor('admin');
    $target = impersonationTarget('editor');

    NotificationPreference::create([
        'user_id' => $target->id,
        'key' => 'core.security.impersonation_started',
        'channel' => 'mail',
        'enabled' => false,
    ]);

    $this->actingAs($actor, 'baobab')->post("/admin/users/{$target->id}/impersonate");

    Notification::assertSentTo($target, BaobabNotification::class);
    Queue::assertPushedOn('baobab', SendQueuedMail::class, fn (SendQueuedMail $job) => $job->to === $target->email);
});
