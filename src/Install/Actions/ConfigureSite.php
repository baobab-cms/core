<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\EnvFile;
use Baobab\Users\Models\RegistrationSetting;

/**
 * Étape 5 de l'installation (spec 15 §4) : l'identité du site.
 *
 * **Deux dépôts, et le partage n'est pas arbitraire** (suivi n° 214). Nom,
 * URL et fuseau horaire vont dans `.env` : `APP_NAME`, `APP_URL` et
 * `APP_TIMEZONE` sont de la configuration Laravel native, que le framework
 * lit déjà de son côté — les recopier en base créerait deux vérités sur le
 * nom du site, et l'une des deux finirait par mentir.
 *
 * L'**inscription front** va en base, parce qu'elle doit rester basculable
 * depuis l'administration : un `.env` ne se modifie pas sans accès aux
 * fichiers, ce que le public visé n'a précisément pas.
 */
final class ConfigureSite
{
    public function __invoke(
        EnvFile $env,
        string $siteName,
        string $url,
        string $timezone = 'UTC',
        bool $registrationOpen = true,
    ): void {
        $env->set([
            'APP_NAME' => $siteName,
            'APP_URL' => rtrim($url, '/'),
            'APP_TIMEZONE' => $timezone,
        ]);

        $setting = RegistrationSetting::current();
        $setting->open = $registrationOpen;
        $setting->save();
    }
}
