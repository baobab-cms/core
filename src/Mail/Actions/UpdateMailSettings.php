<?php

declare(strict_types=1);

namespace Baobab\Mail\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Mail\Models\MailSetting;

/**
 * Met à jour la ligne unique de réglages de transport (spec 13 §2.1, suivi
 * n° 187). Validation faite par l'appelant — patron `UpdateApiSettings`.
 *
 * Le mot de passe SMTP n'est jamais réaffiché à l'écran (patron
 * `WebhookSubscriptionsController::validatedForUpdate()`/`FormSettingsNormalizer`
 * pour le secret du captcha) : un envoi vide le conserve, un envoi non vide
 * l'écrase. Jamais dans l'audit non plus — un secret en clair dans le
 * journal d'audit serait la même fuite que le réafficher à l'écran.
 */
final class UpdateMailSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{mailer: string, from_address: ?string, from_name: ?string, credentials: array<string, mixed>}  $data
     */
    public function __invoke(array $data): MailSetting
    {
        $setting = MailSetting::current();

        $credentials = $data['credentials'];

        if (($credentials['password'] ?? '') === '') {
            $credentials['password'] = $setting->credentials['password'] ?? null;
        }

        $setting->fill([
            'mailer' => $data['mailer'],
            'from_address' => $data['from_address'],
            'from_name' => $data['from_name'],
            'credentials' => $credentials,
        ]);
        $setting->save();

        $this->audit->record('mail.settings.updated', $setting, [
            'mailer' => $data['mailer'],
            'from_address' => $data['from_address'],
            'from_name' => $data['from_name'],
            'credentials' => array_diff_key($credentials, ['password' => null]),
        ]);

        Hook::action('baobab.mail.settings.updated', $setting);

        return $setting;
    }
}
