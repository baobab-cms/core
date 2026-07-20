<?php

use Baobab\Access\Actions\GrantPermission;
use Baobab\Notify\Notifications\BaobabNotification;
use Baobab\Users\Models\User;
use Baobab\Webhooks\Actions\RedeliverWebhookDelivery;
use Baobab\Webhooks\Jobs\DeliverWebhook;
use Baobab\Webhooks\Models\WebhookDelivery;
use Baobab\Webhooks\Models\WebhookSubscription;
use Baobab\Webhooks\Support\WebhookSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('has 5 tries and the ~6h backoff curve from the spec', function () {
    $job = new DeliverWebhook(1, 'baobab.content.saved', ['event' => 'baobab.content.saved']);

    expect($job->tries)->toBe(5)
        ->and($job->backoff())->toBe([300, 900, 3600, 18000]);
});

it('posts a signed, JSON-encoded payload and records a successful delivery', function () {
    Http::fake(['https://example.com/hook' => Http::response(['ok' => true], 200)]);

    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'top-secret-value-1234567890',
        'events' => ['baobab.content.saved'],
        'is_active' => true,
    ]);

    $payload = ['event' => 'baobab.content.saved', 'occurred_at' => now()->toIso8601String(), 'version' => 1, 'data' => [['id' => 1]]];

    (new DeliverWebhook($subscription->id, 'baobab.content.saved', $payload))->handle();

    $expectedSignature = (new WebhookSigner)->sign((string) json_encode($payload), $subscription->secret);

    Http::assertSent(function ($request) use ($expectedSignature, $payload) {
        return $request->url() === 'https://example.com/hook'
            && $request->header('X-Baobab-Signature')[0] === $expectedSignature
            && $request->body() === (string) json_encode($payload);
    });

    $delivery = WebhookDelivery::where('webhook_subscription_id', $subscription->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('success')
        ->and($delivery->response_code)->toBe(200)
        ->and($delivery->payload)->toBe($payload)
        ->and($subscription->fresh()?->consecutive_failures)->toBe(0);
});

it('records a failed delivery and rethrows on a non-2xx response, to trigger the queue retry', function () {
    Http::fake(['https://example.com/hook' => Http::response('server error', 500)]);

    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
        'is_active' => true,
    ]);

    $job = new DeliverWebhook($subscription->id, 'baobab.content.saved', ['event' => 'baobab.content.saved']);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    $delivery = WebhookDelivery::where('webhook_subscription_id', $subscription->id)->first();

    expect($delivery?->status)->toBe('failed')
        ->and($delivery?->response_code)->toBe(500)
        ->and($delivery?->response_body)->toBe('server error');
});

it('truncates an oversized response body when recording a delivery', function () {
    Http::fake(['https://example.com/hook' => Http::response(str_repeat('x', 6000), 500)]);

    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
    ]);

    $job = new DeliverWebhook($subscription->id, 'baobab.content.saved', ['event' => 'baobab.content.saved']);

    try {
        $job->handle();
    } catch (RuntimeException) {
        // attendu — seul le journal nous intéresse ici.
    }

    $delivery = WebhookDelivery::where('webhook_subscription_id', $subscription->id)->first();

    expect(strlen((string) $delivery?->response_body))->toBe(5000);
});

it('increments consecutive_failures on the final failed attempt without re-logging a duplicate delivery', function () {
    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
        'consecutive_failures' => 3,
    ]);

    $job = new DeliverWebhook($subscription->id, 'baobab.content.saved', ['event' => 'baobab.content.saved']);
    $job->failed(new RuntimeException('exhausted'));

    expect($subscription->fresh()?->consecutive_failures)->toBe(4)
        ->and(WebhookDelivery::query()->count())->toBe(0);
});

it('disables the subscription and notifies admins once the failure threshold is reached', function () {
    Notification::fake();
    config(['baobab.webhooks.max_consecutive_failures' => 3]);

    $admin = User::create(['name' => 'Admin', 'email' => 'webhook-admin@example.com', 'password' => 'secret']);
    app(GrantPermission::class)($admin, 'baobab.system.webhooks.manage');

    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
        'consecutive_failures' => 2,
        'is_active' => true,
    ]);

    (new DeliverWebhook($subscription->id, 'baobab.content.saved', ['event' => 'baobab.content.saved']))
        ->failed(new RuntimeException('exhausted'));

    expect($subscription->fresh()?->is_active)->toBeFalse();

    Notification::assertSentTo(
        $admin,
        BaobabNotification::class,
        fn (BaobabNotification $notification): bool => $notification->toDatabase($admin)['key'] === 'core.webhook.subscription_disabled',
    );
});

it('does not disable the subscription before the failure threshold is reached', function () {
    config(['baobab.webhooks.max_consecutive_failures' => 3]);

    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
        'consecutive_failures' => 1,
        'is_active' => true,
    ]);

    (new DeliverWebhook($subscription->id, 'baobab.content.saved', ['event' => 'baobab.content.saved']))
        ->failed(new RuntimeException('exhausted'));

    expect($subscription->fresh()?->is_active)->toBeTrue()
        ->and($subscription->fresh()?->consecutive_failures)->toBe(2);
});

it('redispatches DeliverWebhook with the exact stored payload rather than a freshly rebuilt one', function () {
    Queue::fake();

    $subscription = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['baobab.content.saved'],
    ]);

    $originalPayload = ['event' => 'baobab.content.saved', 'version' => 1, 'data' => [['id' => 42, 'title' => 'Since deleted']]];
    $delivery = WebhookDelivery::create([
        'webhook_subscription_id' => $subscription->id,
        'event' => 'baobab.content.saved',
        'attempt' => 1,
        'payload' => $originalPayload,
        'status' => 'failed',
        'response_code' => 500,
    ]);

    app(RedeliverWebhookDelivery::class)($delivery);

    Queue::assertPushedOn(
        'baobab-low',
        DeliverWebhook::class,
        fn (DeliverWebhook $job) => $job->subscriptionId === $subscription->id
            && $job->event === 'baobab.content.saved'
            && $job->payload === $originalPayload,
    );
});
