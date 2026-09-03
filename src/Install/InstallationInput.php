<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Tout ce qu'une installation demande, en un objet (spec 15 §4).
 *
 * Il existe pour que les deux adaptateurs — la commande et le wizard —
 * remplissent la **même** structure : c'est ce qui rend le §1 vrai en
 * pratique, « un seul pipeline, deux interfaces ». Une commande qui passerait
 * ses propres arguments au pipeline rouvrirait la porte à deux séquences qui
 * divergent.
 */
final readonly class InstallationInput
{
    public function __construct(
        public DatabaseCredentials $database,
        public string $adminEmail,
        public string $siteName,
        public string $url,
        public ?string $adminName = null,
        public ?string $adminPassword = null,
        public string $timezone = 'UTC',
        public bool $registrationOpen = true,
        /**
         * Consentement à la télémétrie (§8 point 2), **faux par défaut**.
         *
         * L'opt-in est explicite : une entrée qui ne dit rien vaut un refus,
         * jamais un consentement implicite. C'est aussi ce que rend le défaut
         * de la colonne, pour que les deux bouts de la chaîne disent la même
         * chose (suivi n° 229, arbitrage A4).
         */
        public bool $telemetry = false,
        public string $appEnv = 'production',
        public string $version = 'dev',
        /**
         * Met en cache config, routes et vues à la finalisation (§4 étape 9).
         *
         * Vrai en production, où c est un gain net. Faux partout où le cache
         * survivrait au geste qui l a écrit — une suite de tests, notamment :
         * un `config:cache` y empoisonne tout ce qui suit, y compris les
         * exécutions ultérieures, le fichier restant sur le disque.
         */
        public bool $optimize = true,
    ) {}
}
