<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Webhooks\Jobs\DeliverWebhook;
use Baobab\Webhooks\Models\WebhookDelivery;

/**
 * Re-livraison manuelle (spec 08 §5) — redispatch `DeliverWebhook` avec le
 * `payload` stocké sur la tentative d'origine, jamais reconstruit : le
 * contenu source peut avoir été modifié ou supprimé depuis.
 */
final class RedeliverWebhookDelivery
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(WebhookDelivery $delivery): void
    {
        DeliverWebhook::dispatch($delivery->webhook_subscription_id, $delivery->event, $delivery->payload)
            ->onConnection('baobab-low')
            ->onQueue('baobab-low');

        $this->audit->record('webhook_delivery.redelivered', $delivery->subscription, ['event' => $delivery->event, 'delivery_id' => $delivery->id]);

        Hook::action('baobab.webhook_delivery.redelivered', $delivery);
    }
}
