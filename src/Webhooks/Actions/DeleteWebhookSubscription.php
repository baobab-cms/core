<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Webhooks\Models\WebhookSubscription;

/**
 * Supprime un abonnement (spec 08 §5) — son journal de livraisons est
 * supprimé avec lui (`cascadeOnDelete` en base). Patron exact
 * `DeleteRedirect`.
 */
final class DeleteWebhookSubscription
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(WebhookSubscription $subscription): void
    {
        $this->audit->record('webhook_subscription.deleted', $subscription, ['url' => $subscription->url]);

        $subscription->delete();

        Hook::action('baobab.webhook_subscription.deleted', $subscription);
    }
}
