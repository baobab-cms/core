<?php

declare(strict_types=1);

namespace Baobab\Privacy\Cookies;

use Baobab\Auth\RememberDuration;
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
        $rememberDays = RememberDuration::days();

        $cookies = [
            $this->core((string) config('session.cookie'), 'session', $sessionDuration),
            $this->core('XSRF-TOKEN', 'xsrf', $sessionDuration),
            // Laravel nomme ce cookie `remember_{garde}_{sha1}` (spec 04 §9).
            $this->core('remember_baobab_*', 'remember', trans_choice('baobab::privacy.cookies.durations.days', $rememberDays, ['count' => $rememberDays])),
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
