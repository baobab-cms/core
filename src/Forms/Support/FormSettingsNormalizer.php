<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

use Baobab\Forms\Captcha\CaptchaProviders;

/**
 * Normalisation des réglages de suites (spec 14 §8) et d'anti-spam (§7) saisis
 * à l'écran `admin/forms/{form}/edit` (M8 point 6, Pass B3) — stockés dans
 * `forms.settings`, un sac JSON depuis la Pass A, resté sans forme concrète
 * jusqu'à cette passe.
 *
 * Le niveau 1 de l'anti-spam (honeypot, piège temporel, rate limiting) et le
 * niveau 2 (marquage spam via le hook `baobab.form.spam_checking`) n'ont
 * **aucun réglage ici** : le premier est toujours actif (§7.1, « coût
 * utilisateur : zéro »), le second se câble par un module, pas par un
 * formulaire. Seul le niveau 3 (captcha, optionnel par formulaire) a une
 * configuration, exécutée réellement depuis la Pass D3 (`CaptchaProviders`).
 *
 * `confirmation.message` ajouté en Pass C1 (spec 14 §4, « message de
 * confirmation configurable ») : chaîne vide ou absente → le contrôleur
 * public retombe sur le texte par défaut (`rendering.form_confirmation_default`),
 * jamais stocké ici.
 */
final class FormSettingsNormalizer
{
    /**
     * Liste dérivée de `CaptchaProviders::keys()` (Pass D3) plutôt qu'un
     * tableau fermé : un module qui enregistre un provider via
     * `Hook::modify('baobab.forms.captcha.providers', ...)` doit pouvoir le
     * sélectionner ici, sinon la promesse d'extensibilité de la spec §11
     * décision 2 ne tiendrait que côté vérification, jamais côté réglage.
     *
     * @return list<string>
     */
    public static function captchaProviders(): array
    {
        return ['none', ...CaptchaProviders::keys()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  string|null  $existingCaptchaSecretKey  Valeur déjà enregistrée
     *                                                 (Pass D3) : un champ laissé vide à l'édition conserve le
     *                                                 secret existant plutôt que de l'effacer — jamais réaffiché en
     *                                                 clair après enregistrement (patron `WebhookSubscriptionsController::validatedForUpdate()`).
     * @return array<string, mixed>
     */
    public static function normalize(array $input, ?string $existingCaptchaSecretKey = null): array
    {
        $provider = (string) ($input['captcha_provider'] ?? 'none');

        if (! in_array($provider, self::captchaProviders(), true)) {
            $provider = 'none';
        }

        return [
            'confirmation' => [
                'message' => self::nullableString($input['confirmation_message'] ?? null),
            ],
            'suites' => [
                'email_notification' => [
                    'enabled' => (bool) ($input['suites']['email_notification']['enabled'] ?? false),
                    'recipients' => self::normalizeRecipients($input['suites']['email_notification']['recipients'] ?? null),
                ],
                'acknowledgement' => [
                    'enabled' => (bool) ($input['suites']['acknowledgement']['enabled'] ?? false),
                ],
                'admin_notification' => [
                    'enabled' => (bool) ($input['suites']['admin_notification']['enabled'] ?? false),
                ],
                'webhook' => [
                    'enabled' => (bool) ($input['suites']['webhook']['enabled'] ?? false),
                ],
            ],
            'anti_spam' => [
                'captcha' => [
                    'provider' => $provider,
                    'site_key' => $provider === 'none' ? null : self::nullableString($input['captcha_site_key'] ?? null),
                    'secret_key' => $provider === 'none'
                        ? null
                        : (self::nullableString($input['captcha_secret_key'] ?? null) ?? $existingCaptchaSecretKey),
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizeRecipients(mixed $recipients): array
    {
        if (! is_string($recipients)) {
            return [];
        }

        $lines = array_map(trim(...), explode("\n", $recipients));

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
