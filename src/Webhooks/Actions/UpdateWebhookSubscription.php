<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Webhooks\Models\WebhookSubscription;

/**
 * Met à jour un abonnement existant (spec 08 §5). Patron exact
 * `CreateWebhookSubscription`. Une réactivation manuelle (`is_active: true`
 * après une désactivation automatique) remet `consecutive_failures` à zéro
 * — sinon un seul nouvel échec suffirait à redésactiver immédiatement.
 */
final class UpdateWebhookSubscription
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{url?: string, secret?: string, events?: list<string>, is_active?: bool}  $data
     */
    public function __invoke(WebhookSubscription $subscription, array $data): WebhookSubscription
    {
        if (($data['is_active'] ?? false) && ! $subscription->is_active) {
            $data['consecutive_failures'] = 0;
        }

        $subscription->fill($data);
        $subscription->save();

        $this->audit->record('webhook_subscription.updated', $subscription, [
            'url' => $subscription->url,
            'events' => $subscription->events,
            'is_active' => $subscription->is_active,
        ]);

        Hook::action('baobab.webhook_subscription.updated', $subscription);

        return $subscription;
    }
}
