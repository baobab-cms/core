<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Actions;

use Baobab\Webhooks\Jobs\DeliverWebhook;
use Baobab\Webhooks\Models\WebhookSubscription;
use Baobab\Webhooks\Support\WebhookPayloadBuilder;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Invoquée par le listener générique câblé sur chaque hook du catalogue
 * (`BaobabServiceProvider::registerWebhookDispatchListeners()`/
 * `bootstrapActiveModules()`). Garde `Schema::hasTable()` (patron
 * `bootstrapActiveModules()`/`usersWithPermission()`) : un hook du Core peut
 * se déclencher avant que les migrations de ce module aient tourné (install
 * fraîche, tests d'un autre domaine) — no-op silencieux plutôt que de casser
 * l'action appelante.
 */
final class DispatchWebhookEvent
{
    public function __construct(private readonly WebhookPayloadBuilder $payloadBuilder) {}

    /**
     * @param  list<mixed>  $args
     */
    public function __invoke(string $event, array $args): void
    {
        try {
            if (! Schema::hasTable('webhook_subscriptions')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $subscriptions = WebhookSubscription::query()->where('is_active', true)->get()
            ->filter(fn (WebhookSubscription $subscription): bool => $subscription->subscribesTo($event));

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = $this->payloadBuilder->build($event, $args);

        foreach ($subscriptions as $subscription) {
            DeliverWebhook::dispatch($subscription->id, $event, $payload)
                ->onConnection('baobab-low')
                ->onQueue('baobab-low');
        }
    }
}
