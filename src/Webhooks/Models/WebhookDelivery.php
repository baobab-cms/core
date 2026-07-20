<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une tentative de livraison (spec 08 §5) — une ligne par essai, y compris
 * les retries automatiques et les re-livraisons manuelles
 * (`Baobab\Webhooks\Actions\RedeliverWebhookDelivery`). `payload` est le
 * snapshot réellement envoyé, jamais reconstruit après coup.
 *
 * @property int $id
 * @property int $webhook_subscription_id
 * @property string $event
 * @property int $attempt
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int|null $response_code
 * @property int|null $duration_ms
 * @property string|null $response_body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookDelivery extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'webhook_subscription_id',
        'event',
        'attempt',
        'payload',
        'status',
        'response_code',
        'duration_ms',
        'response_body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt' => 'integer',
            'response_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
