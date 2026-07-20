<?php

use Baobab\Facades\Hook;
use Baobab\Webhooks\Actions\DispatchWebhookEvent;
use Baobab\Webhooks\Jobs\DeliverWebhook;
use Baobab\Webhooks\Models\WebhookSubscription;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Invoque `DispatchWebhookEvent` directement plutôt que de déclencher un
 * vrai hook Core via `Hook::action()` — `baobab.content.saved` porte déjà
 * d'autres listeners réels et fortement typés (ex. `registerMediaUsageListener()`)
 * qui rejetteraient des arguments factices ; ce test isole la logique de
 * dispatch elle-même, indépendamment du câblage fait dans
 * `BaobabServiceProvider::registerWebhookDispatchListeners()` (une simple
 * boucle `HookRegistry::listen()`, correcte par inspection).
 */
it('dispatches DeliverWebhook to every active subscription matching the event, and no other', function () {
    Queue::fake();

    $matching = WebhookSubscription::create([
        'url' => 'https://example.com/hook-a',
        'secret' => str_repeat('a', 32),
        'events' => ['acme.thing.happened'],
        'is_active' => true,
    ]);
    WebhookSubscription::create([
        'url' => 'https://example.com/hook-b',
        'secret' => str_repeat('a', 32),
        'events' => ['acme.other.happened'],
        'is_active' => true,
    ]);
    WebhookSubscription::create([
        'url' => 'https://example.com/hook-c',
        'secret' => str_repeat('a', 32),
        'events' => ['acme.thing.happened'],
        'is_active' => false,
    ]);

    app(DispatchWebhookEvent::class)('acme.thing.happened', [['id' => 1, 'title' => 'Hello']]);

    Queue::assertPushed(DeliverWebhook::class, 1);
    Queue::assertPushedOn(
        'baobab-low',
        DeliverWebhook::class,
        fn (DeliverWebhook $job) => $job->subscriptionId === $matching->id && $job->event === 'acme.thing.happened',
    );
});

it('builds the payload envelope and applies the baobab.webhook.payload filter', function () {
    Queue::fake();

    WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => str_repeat('a', 32),
        'events' => ['acme.thing.happened'],
        'is_active' => true,
    ]);

    Hook::modify('baobab.webhook.payload', function (array $payload) {
        $payload['data'][] = 'enriched';

        return $payload;
    });

    app(DispatchWebhookEvent::class)('acme.thing.happened', [['id' => 1]]);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->payload['event'] === 'acme.thing.happened'
            && $job->payload['version'] === 1
            && $job->payload['data'][0] === ['id' => 1]
            && $job->payload['data'][1] === 'enriched';
    });
});

it('does nothing when the webhook_subscriptions table is unavailable', function () {
    Schema::shouldReceive('hasTable')->with('webhook_subscriptions')->andReturn(false);
    Queue::fake();

    app(DispatchWebhookEvent::class)('acme.thing.happened', [['id' => 1]]);

    Queue::assertNotPushed(DeliverWebhook::class);
});
