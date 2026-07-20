<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Support;

use Baobab\Facades\Hook;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

/**
 * Construit l'enveloppe JSON envoyée à un abonné (spec 08 §5 : « événement,
 * date, données, version »). Un hook se déclenche avec des arguments
 * positionnels variadiques (modèles Eloquent, scalaires, tableaux — jamais
 * un objet enveloppe) : chaque argument est sérialisé génériquement, sans
 * mapping par événement à écrire à la main. Filtre `baobab.webhook.payload`
 * (spec 08 §5 dernier point) : un module peut enrichir/expurger le payload
 * de ses propres événements.
 */
final class WebhookPayloadBuilder
{
    /**
     * @param  list<mixed>  $args
     * @return array<string, mixed>
     */
    public function build(string $event, array $args): array
    {
        $payload = [
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'version' => 1,
            'data' => array_map($this->serialize(...), $args),
        ];

        /** @var array<string, mixed> $filtered */
        $filtered = Hook::filter('baobab.webhook.payload', $payload, $event);

        return $filtered;
    }

    private function serialize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Model => $value->toArray(),
            $value instanceof Arrayable => $value->toArray(),
            default => $value,
        };
    }
}
