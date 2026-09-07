<?php

declare(strict_types=1);

namespace Baobab\Mail\Support;

use Baobab\Mail\Models\MailSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Surcharge `config('mail.*')` depuis le réglage persisté (spec 13 §2.1,
 * suivi n° 187) — aucun mécanisme équivalent n'existait dans ce Core :
 * Branding/API/Reading sont des concepts propres à Baobab, jamais une
 * surcharge de configuration native Laravel. Appelée depuis
 * `BaobabServiceProvider::register()`, qui s'exécute à chaque bootstrap
 * (requête web comme job de queue) — `config()` n'a donc jamais de valeur
 * périmée d'une exécution précédente.
 *
 * Extraite du service provider pour rester directement testable : y
 * accéder au travers d'un cycle de boot complet dépendrait de l'ordre
 * migrations/providers, que Testbench ne garantit pas de la même façon
 * qu'une requête réelle.
 *
 * Absente de toute donnée (aucun admin n'a visité l'écran, ou host vide) :
 * ne touche à rien, `.env`/`config/mail.php` restent seuls maîtres —
 * jamais de régression silencieuse d'une installation qui n'utilise pas
 * cet écran. SMTP seul pour cette passe : les autres mailers restent
 * pilotables par `.env` comme aujourd'hui.
 */
final class MailTransportConfigurator
{
    public function apply(): void
    {
        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $setting = MailSetting::query()->find(1);

        if ($setting === null || $setting->mailer !== 'smtp') {
            return;
        }

        /** @var array<string, mixed> $credentials */
        $credentials = $setting->credentials;
        $host = (string) ($credentials['host'] ?? '');

        if ($host === '') {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) ($credentials['port'] ?? 587),
            'mail.mailers.smtp.username' => $credentials['username'] ?? null,
            'mail.mailers.smtp.password' => $credentials['password'] ?? null,
            'mail.mailers.smtp.scheme' => ($credentials['scheme'] ?? '') !== '' ? $credentials['scheme'] : null,
        ]);

        if ($setting->from_address !== null) {
            config(['mail.from.address' => $setting->from_address]);
        }

        if ($setting->from_name !== null) {
            config(['mail.from.name' => $setting->from_name]);
        }
    }
}
