<?php

declare(strict_types=1);

namespace Baobab\Webhooks\Support;

/**
 * Signature HMAC-SHA256 du corps envoyé (spec 08 §5, en-tête
 * `X-Baobab-Signature`) — signe les octets réellement transmis (le JSON déjà
 * encodé), jamais le tableau PHP avant encodage, pour que l'abonné puisse
 * vérifier la signature sur le corps brut de sa requête reçue.
 */
final class WebhookSigner
{
    public function sign(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }
}
