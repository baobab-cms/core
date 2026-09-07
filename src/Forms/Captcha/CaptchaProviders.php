<?php

declare(strict_types=1);

namespace Baobab\Forms\Captcha;

use Baobab\Facades\Hook;

/**
 * Registre des fournisseurs captcha (spec 14 §7.3, §11 décision 2), patron
 * `Baobab\Media\Support\OEmbedResolver::PROVIDERS` : liste par défaut,
 * filtrée par `Hook::filter('baobab.forms.captcha.providers', ...)` — un
 * module tiers (reCAPTCHA, écarté du core par choix de vie privée) s'ajoute
 * en un seul point, consommé à la fois par la vérification (`resolve()`) et
 * par le `<select>` de l'écran de réglages (`keys()`) : sans ce second
 * point, un provider ajouté par un module ne serait jamais sélectionnable.
 *
 * Pas de libellé ici : c'est un détail d'écran admin (traduit, patron
 * `admin.forms.captcha_provider_*`), sans rapport avec la vérification ou le
 * rendu du widget — `FormsController::captchaProviderOptions()` s'en charge,
 * avec un repli généré pour un provider ajouté par un module qui n'aurait
 * pas sa propre traduction.
 *
 * @phpstan-type ProviderDefinition array{class: class-string<CaptchaProvider>, widget_class: string, script_src: string, response_field: string}
 */
final class CaptchaProviders
{
    /**
     * @var array<string, ProviderDefinition>
     */
    private const PROVIDERS = [
        'turnstile' => [
            'class' => TurnstileProvider::class,
            'widget_class' => 'cf-turnstile',
            'script_src' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'response_field' => 'cf-turnstile-response',
        ],
        'hcaptcha' => [
            'class' => HcaptchaProvider::class,
            'widget_class' => 'h-captcha',
            'script_src' => 'https://js.hcaptcha.com/1/api.js',
            'response_field' => 'h-captcha-response',
        ],
    ];

    /**
     * @return array<string, ProviderDefinition>
     */
    public static function all(): array
    {
        /** @var array<string, ProviderDefinition> $providers */
        $providers = Hook::filter('baobab.forms.captcha.providers', self::PROVIDERS);

        return $providers;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return ProviderDefinition|null
     */
    public static function definition(string $provider): ?array
    {
        return self::all()[$provider] ?? null;
    }

    public static function resolve(string $provider): ?CaptchaProvider
    {
        $definition = self::definition($provider);

        if ($definition === null) {
            return null;
        }

        return app($definition['class']);
    }
}
