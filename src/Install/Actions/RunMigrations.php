<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\Exceptions\InstallationStepFailed;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

/**
 * Étape 3 de l'installation (spec 15 §4) : les migrations, et rien de plus.
 *
 * **Il n'y a pas de seeder à appeler ici**, contrairement à ce que la spec
 * annonçait jusqu'au 25 août 2026 (suivi n° 212) : les rôles et permissions
 * par défaut sont posés par des migrations — `seed_default_roles`,
 * `grant_admin_access_to_default_roles`. Une migration s'exécute toujours ;
 * un seeder demande à être appelé, ce qu'aucune installation par archive ne
 * garantit.
 *
 * `--force` est indispensable et non un raccourci : `migrate` refuse de
 * s'exécuter sans confirmation en environnement de production, et une
 * installation graphique n'a personne pour répondre à une invite console.
 */
final class RunMigrations
{
    public function __construct(private readonly Kernel $artisan) {}

    public function __invoke(): int
    {
        try {
            $status = $this->artisan->call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            throw InstallationStepFailed::migrations(
                'La création des tables a échoué. La base est joignable, mais l\'utilisateur '
                .'qui s\'y connecte n\'a peut-être pas le droit de créer des tables — '
                .'vérifiez ses privilèges auprès de votre hébergement.',
                $e,
            );
        }

        if ($status !== 0) {
            throw InstallationStepFailed::migrations(
                'La création des tables s\'est interrompue. Relancez l\'installation : '
                .'elle reprendra à cette étape sans refaire les précédentes.',
            );
        }

        return $status;
    }
}
