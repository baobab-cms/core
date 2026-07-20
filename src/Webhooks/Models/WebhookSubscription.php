<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un abonnement webhook sortant (spec 08 §5) — URL cible, secret partagé
 * (chiffré au repos), sous-ensemble d'événements du catalogue
 * (`Baobab\Webhooks\Support\WebhookEventCatalog`). `consecutive_failures`
 * pilote la désactivation automatique (`Baobab\Webhooks\Jobs\DeliverWebhook::failed()`).
 *
 * @property int $id
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property Carbon|null $last_triggered_at
 */
class WebhookSubscription extends Model
{
    /**
     * `consecutive_failures`/`last_triggered_at` n'arrivent jamais depuis une
     * requête admin brute (`WebhookSubscriptionsController::validated()` ne
     * construit son tableau qu'à partir de `url`/`secret`/`events`/`is_active`,
     * jamais d'un passthrough de `$request->all()`) — seuls `DeliverWebhook`
     * et `UpdateWebhookSubscription` (reset au réactivation) les assignent,
     * du code interne de confiance.
     *
     * @var list<string>
     */
    protected $fillable = [
        'url',
        'secret',
        'events',
        'is_active',
        'consecutive_failures',
        'last_triggered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'secret' => 'encrypted',
            'consecutive_failures' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events, true);
    }
}
