<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\Capability;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * Étape 6 de l'installation (spec 15 §4) : on ferme derrière soi.
 *
 * **L'étape ne peut pas échouer sur une capacité que l'hébergeur refuse**
 * (§4.1). C'était le défaut de la version 0.2 de la spec, relevé au n° 174 :
 * `storage:link` appelé sans condition faisait échouer l'étape finale
 * précisément chez la cible du produit. Ici, un `symlink()` indisponible
 * n'arrête rien — le repli est documenté dans la checklist, pas subi.
 *
 * **Le lock s'écrit en dernier**, et c'est lui qui neutralise l'installateur :
 * dès qu'il existe, les routes `/install/*` ne sont plus enregistrées du tout
 * (§3, §6.3). Tout ce qui pourrait encore échouer doit donc être passé avant
 * — sans quoi on neutraliserait l'installateur au milieu d'une installation
 * inachevée, sans moyen de la reprendre.
 */
final class FinalizeInstallation
{
    public function __construct(
        private readonly InstallationState $state,
        private readonly Filesystem $files,
        private readonly Kernel $artisan,
    ) {}

    /**
     * @param  string  $version  version installée, journalisée dans le lock
     * @param  bool  $optimize  met en cache config, routes et vues (§4 étape 6)
     */
    public function __invoke(string $version, HostingProfile $profile, string $configChecksum, bool $optimize = true): HostingProfile
    {
        $profile = $this->linkStorage($profile);

        if ($optimize) {
            $this->optimize();
        }

        $this->removeInstallerSurface();

        // En dernier : il neutralise l'installateur (§6.3).
        $this->state->markInstalled($version, $profile, $configChecksum);

        return $profile;
    }

    /**
     * Publie le stockage, ou constate qu'on ne peut pas.
     *
     * Le profil rendu peut différer de celui reçu : `symlink()` peut exister
     * et échouer quand même — droits, système de fichiers, `open_basedir`.
     * Le lock doit porter ce qui s'est **réellement** passé, pas ce qu'on
     * avait détecté avant d'essayer.
     */
    private function linkStorage(HostingProfile $profile): HostingProfile
    {
        if (! $profile->symlink->isPresent()) {
            return $profile;
        }

        try {
            $this->artisan->call('storage:link');
        } catch (Throwable) {
            return new HostingProfile(
                symlink: Capability::Absent,
                procOpen: $profile->procOpen,
                shellAccess: $profile->shellAccess,
                publicIsDocumentRoot: $profile->publicIsDocumentRoot,
                webServer: $profile->webServer,
            );
        }

        return $profile;
    }

    /**
     * Les caches sont un confort, jamais une condition.
     *
     * Un `config:cache` qui échoue sur un hébergement bridé ne doit pas
     * empêcher un site par ailleurs installé de démarrer — il démarrera
     * simplement moins vite.
     */
    private function optimize(): void
    {
        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            try {
                $this->artisan->call($command);
            } catch (Throwable) {
                // Ignoré à dessein : voir le commentaire ci-dessus.
            }
        }
    }

    /**
     * Retire la surface exposée de l'installateur (§6.3).
     *
     * Le code PHP, lui, reste dans `vendor/` : il ne peut rien exécuter sans
     * routes, et il resservira à `baobab:check`. Supprimer du code vendor
     * casserait les mises à jour Composer.
     */
    private function removeInstallerSurface(): void
    {
        $this->files->deleteDirectory(public_path('install'));
        $this->files->delete(storage_path('app/baobab/install-token.txt'));
    }
}
