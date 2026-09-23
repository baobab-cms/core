<?php

declare(strict_types=1);

namespace Baobab\Privacy\Cookies;

use Baobab\Forms\Models\Form;

/**
 * Cookies déposés par le Core lui-même (spec 16 §3.2), tous `necessary` :
 * les déclarer n'affiche aucune bannière (décision 21), mais l'écran de
 * consultation (Pass F2) doit pouvoir les lister.
 *
 * Les captchas (spec 14 §7.3) ne sont déclarés que si au moins un
 * formulaire les utilise (décision 22) : un site sans captcha ne charge
 * aucun de leurs scripts. Les fournisseurs ajoutés par module
 * (`baobab.forms.captcha.providers`) se déclarent dans leur propre
 * manifeste.
 */
final class CoreCookies
{
    public const string CONSENT_COOKIE = 'baobab_consent';

    /** Durée du cookie « se souvenir de moi » de Laravel (`SessionGuard::$rememberDuration`). */
    private const int REMEMBER_DAYS = 400;

    private const array CAPTCHAS = [
        'turnstile' => ['name' => 'Turnstile', 'provider' => 'Cloudflare (challenges.cloudflare.com)'],
        'hcaptcha' => ['name' => 'hCaptcha', 'provider' => 'Intuition Machines (hcaptcha.com)'],
    ];

    /**
     * @return list<CookieDeclaration>
     */
    public function __invoke(): array
    {
        $lifetime = (int) config('session.lifetime');
        $sessionDuration = (bool) config('session.expire_on_close')
            ? __('baobab::privacy.cookies.durations.browser_session')
            : trans_choice('baobab::privacy.cookies.durations.minutes', $lifetime, ['count' => $lifetime]);

        $consentDays = (int) config('baobab.privacy.consent_lifetime_days');

        $cookies = [
            $this->core((string) config('session.cookie'), 'session', $sessionDuration),
            $this->core('XSRF-TOKEN', 'xsrf', $sessionDuration),
            $this->core('remember_web_*', 'remember', trans_choice('baobab::privacy.cookies.durations.days', self::REMEMBER_DAYS, ['count' => self::REMEMBER_DAYS])),
            $this->core(self::CONSENT_COOKIE, 'consent', trans_choice('baobab::privacy.cookies.durations.days', $consentDays, ['count' => $consentDays])),
        ];

        foreach (self::CAPTCHAS as $key => $captcha) {
            if (! Form::query()->where('settings->anti_spam->captcha->provider', $key)->exists()) {
                continue;
            }

            $cookies[] = new CookieDeclaration(
                name: $captcha['name'],
                category: CookieCategory::Necessary,
                purpose: __('baobab::privacy.cookies.core.captcha'),
                duration: __('baobab::privacy.cookies.durations.provider_defined'),
                provider: $captcha['provider'],
                source: 'core',
            );
        }

        return $cookies;
    }

    private function core(string $name, string $key, string $duration): CookieDeclaration
    {
        return new CookieDeclaration(
            name: $name,
            category: CookieCategory::Necessary,
            purpose: __("baobab::privacy.cookies.core.{$key}"),
            duration: $duration,
            provider: null,
            source: 'core',
        );
    }
}
