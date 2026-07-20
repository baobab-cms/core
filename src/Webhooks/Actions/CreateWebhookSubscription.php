<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Webhooks\Models\WebhookSubscription;

/**
 * Crée un abonnement webhook (spec 08 §5). Validation faite par l'appelant
 * (patron `CreateRedirect`).
 */
final class CreateWebhookSubscription
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{url: string, secret: string, events: list<string>, is_active?: bool}  $data
     */
    public function __invoke(array $data): WebhookSubscription
    {
        $subscription = WebhookSubscription::create($data);

        // Jamais le secret en clair dans l'audit.
        $this->audit->record('webhook_subscription.created', $subscription, ['url' => $data['url'], 'events' => $data['events']]);

        Hook::action('baobab.webhook_subscription.created', $subscription);

        return $subscription;
    }
}
