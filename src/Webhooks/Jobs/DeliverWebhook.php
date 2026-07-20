<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Jobs;

use Baobab\Notify\Notifier;
use Baobab\Users\Models\User;
use Baobab\Webhooks\Models\WebhookDelivery;
use Baobab\Webhooks\Models\WebhookSubscription;
use Baobab\Webhooks\Support\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Livraison effective d'un webhook (spec 08 §5) — jamais synchrone dans la
 * requête HTTP. Patron `Baobab\Mail\Jobs\SendQueuedMail`, avec les valeurs de
 * la spec (« 5 tentatives sur ~6h ») : 5min/15min/1h/5h de backoff, la 5e et
 * dernière tentative survenant ~6h20 après la première. `$payload` est déjà
 * construit à l'émission de l'événement (jamais des instances Eloquent
 * brutes) : reste valide même si l'entité source disparaît avant une retry
 * planifiée plusieurs heures plus tard.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $event,
        public readonly array $payload,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [300, 900, 3600, 18000];
    }

    public function handle(): void
    {
        $subscription = WebhookSubscription::find($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $signer = new WebhookSigner;
        $body = (string) json_encode($this->payload);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withBody($body, 'application/json')
                ->withHeaders(['X-Baobab-Signature' => $signer->sign($body, $subscription->secret)])
                ->post($subscription->url);
        } catch (Throwable $e) {
            $this->recordDelivery($subscription, 'failed', null, $this->elapsedMs($startedAt), $e->getMessage());

            throw $e;
        }

        $durationMs = $this->elapsedMs($startedAt);

        if (! $response->successful()) {
            $this->recordDelivery($subscription, 'failed', $response->status(), $durationMs, $response->body());

            throw new RuntimeException("Webhook delivery failed with status {$response->status()} for subscription {$subscription->id}.");
        }

        $this->recordDelivery($subscription, 'success', $response->status(), $durationMs, $response->body());

        $subscription->update(['consecutive_failures' => 0, 'last_triggered_at' => now()]);
    }

    /**
     * Dernier essai épuisé — `handle()` a déjà journalisé cette tentative,
     * cette méthode ne fait que le décompte des échecs consécutifs et la
     * désactivation automatique (spec 08 §5).
     */
    public function failed(Throwable $exception): void
    {
        $subscription = WebhookSubscription::find($this->subscriptionId);

        if ($subscription === null) {
            return;
        }

        $subscription->increment('consecutive_failures');
        $subscription->refresh();

        $threshold = (int) config('baobab.webhooks.max_consecutive_failures', 10);

        if ($subscription->consecutive_failures < $threshold) {
            return;
        }

        $subscription->update(['is_active' => false]);

        app(Notifier::class)->send(
            'core.webhook.subscription_disabled',
            User::permission('baobab.system.webhooks.manage')->get(),
            ['url' => $subscription->url, 'consecutive_failures' => $subscription->consecutive_failures],
        );
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) ((microtime(true) - $startedAt) * 1000);
    }

    private function recordDelivery(
        WebhookSubscription $subscription,
        string $status,
        ?int $responseCode,
        ?int $durationMs,
        ?string $responseBody,
    ): void {
        WebhookDelivery::create([
            'webhook_subscription_id' => $subscription->id,
            'event' => $this->event,
            'attempt' => $this->attempts(),
            'payload' => $this->payload,
            'status' => $status,
            'response_code' => $responseCode,
            'duration_ms' => $durationMs,
            'response_body' => $responseBody !== null ? Str::limit($responseBody, 5000, '') : null,
        ]);
    }
}
