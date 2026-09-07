<?php

declare(strict_types=1);

namespace Baobab\Forms\Captcha;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile (spec 14 §7.3). Patron `OEmbedResolver`/`CreateExternalMedia` :
 * `Http::timeout()` synchrone (jamais en queue — le résultat conditionne
 * directement la réponse à la requête de soumission), échec réseau traité
 * comme un échec de vérification, jamais une exception qui remonterait.
 */
final class TurnstileProvider implements CaptchaProvider
{
    private const VERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, string $secretKey, ?string $remoteIp = null): bool
    {
        try {
            $response = Http::timeout(10)->asForm()->post(self::VERIFY_ENDPOINT, array_filter([
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));
        } catch (Throwable) {
            return false;
        }

        return $response->successful() && (bool) $response->json('success');
    }
}
