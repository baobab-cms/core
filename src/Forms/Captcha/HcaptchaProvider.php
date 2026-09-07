<?php

declare(strict_types=1);

namespace Baobab\Forms\Captcha;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * hCaptcha (spec 14 §7.3). Même patron que `TurnstileProvider` — les deux
 * fournisseurs v1 partagent le même protocole `siteverify` (secret + réponse
 * + IP optionnelle en POST form-encodé, `success` booléen dans la réponse
 * JSON), seul l'endpoint diffère.
 */
final class HcaptchaProvider implements CaptchaProvider
{
    private const VERIFY_ENDPOINT = 'https://hcaptcha.com/siteverify';

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
