<?php

declare(strict_types=1);

namespace Baobab\Forms\Captcha;

/**
 * Interface d'un fournisseur de captcha (spec 14 §7.3, M8 point 6 Pass D3) —
 * extensible par module (spec §11 décision 2 : « reCAPTCHA écarté du core,
 * possible via module tiers grâce à l'interface provider »). Vérification
 * seule : le rendu du widget (script, classe CSS du conteneur) vit dans
 * `CaptchaProviders`, à côté de la classe de vérification, un seul endroit
 * pour tout ce qu'un provider doit fournir.
 */
interface CaptchaProvider
{
    public function verify(string $token, string $secretKey, ?string $remoteIp = null): bool;
}
