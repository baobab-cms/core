<?php

declare(strict_types=1);

namespace Baobab\Admin\Webhooks\Http\Controllers;

use Baobab\Webhooks\Actions\RedeliverWebhookDelivery;
use Baobab\Webhooks\Models\WebhookDelivery;
use Baobab\Webhooks\Models\WebhookSubscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Journal des livraisons (spec 08 §5) — sous-écran de « Webhooks », patron
 * exact `NotFoundLogController`. Une ligne par tentative (premier essai,
 * retries automatiques, re-livraisons manuelles).
 */
final class WebhookDeliveriesController
{
    public function index(WebhookSubscription $subscription): View
    {
        $deliveries = $subscription->deliveries()
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('baobab::admin.webhooks.deliveries', [
            'subscription' => $subscription,
            'deliveries' => $deliveries,
            'columns' => $this->columns(),
        ]);
    }

    public function redeliver(WebhookDelivery $delivery, RedeliverWebhookDelivery $action): RedirectResponse
    {
        $action($delivery);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.webhooks.redelivered')]);

        return redirect()->route('admin.webhooks.deliveries.index', ['subscription' => $delivery->webhook_subscription_id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            ['key' => 'event', 'label' => __('baobab::admin.webhooks.column_event')],
            ['key' => 'attempt', 'label' => __('baobab::admin.webhooks.column_attempt')],
            [
                'key' => 'status',
                'label' => __('baobab::admin.webhooks.column_status'),
                'render' => fn (WebhookDelivery $delivery) => $delivery->status === 'success' ? __('baobab::admin.webhooks.status_success') : __('baobab::admin.webhooks.status_failed'),
            ],
            ['key' => 'response_code', 'label' => __('baobab::admin.webhooks.column_response_code')],
            ['key' => 'duration_ms', 'label' => __('baobab::admin.webhooks.column_duration')],
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.webhooks.column_date'),
                'render' => fn (WebhookDelivery $delivery) => $delivery->created_at->format('Y-m-d H:i:s'),
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (WebhookDelivery $delivery) => view('baobab::admin.webhooks.partials.redeliver-action', ['delivery' => $delivery])->render(),
            ],
        ];
    }
}
