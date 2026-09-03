<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\EnvFile;
use Baobab\Telemetry\Models\TelemetrySetting;
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
 *
 * **Le consentement à la télémétrie suit exactement cette raison-là**
 * (suivi n° 229, arbitrage A4). Il était jusqu'ici collecté par le wizard puis
 * perdu : le brouillon le portait, et `forget()` l'effaçait à la finalisation.
 * Un consentement qui ne survit pas à l'écran qui l'a recueilli n'en est pas
 * un — et celui-ci doit en plus pouvoir se retirer, ce que `.env` interdit au
 * public visé.
 */
final class ConfigureSite
{
    public function __invoke(
        EnvFile $env,
        string $siteName,
        string $url,
        string $timezone = 'UTC',
        bool $registrationOpen = true,
        bool $telemetry = false,
    ): void {
        $env->set([
            'APP_NAME' => $siteName,
            'APP_URL' => rtrim($url, '/'),
            'APP_TIMEZONE' => $timezone,
            // **Le nom du cookie de session est figé ici, et c'est un
            // correctif, pas une précaution.** `config/session.php` le dérive
            // de `APP_NAME` (`Str::slug(APP_NAME).'-session'`) : écrire le nom
            // du site le déplaçait donc, et la requête suivante cherchait un
            // cookie que le navigateur n'avait pas. La session d'installation
            // — qui porte le verrou et le brouillon — disparaissait entre
            // l'étape 6 et la finalisation, `EnsureInstallSession` renvoyait à
            // la porte, et le wizard recevait du HTML là où il attendait du
            // JSON. Trouvé en recette le 2 septembre 2026 sur mutualisé réel
            // (suivi n° 226) ; invisible aux tests, où la configuration n'est
            // pas relue entre deux requêtes.
            //
            // La valeur figée est celle **en vigueur**, jamais une dérivée du
            // nouveau nom : changer le cookie maintenant orphelinerait la
            // session courante, c'est-à-dire refaire le défaut.
            //
            // Le bénéfice dépasse l'installation : sans ce verrou, renommer le
            // site depuis l'administration déconnecterait tout le monde, y
            // compris l'administrateur au milieu de son geste.
            'SESSION_COOKIE' => (string) config('session.cookie'),
        ]);

        $setting = RegistrationSetting::current();
        $setting->open = $registrationOpen;
        $setting->save();

        $telemetrySetting = TelemetrySetting::current();
        $telemetrySetting->enabled = $telemetry;
        $telemetrySetting->save();
    }
}
