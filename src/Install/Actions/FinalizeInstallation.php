<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Install\HostingProfile;
use Baobab\Install\InstallationState;
use Baobab\Install\InstallToken;
use Baobab\Telemetry\Models\TelemetrySetting;
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
        private readonly InstallToken $token,
        private readonly AuditLogger $audit,
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

        $this->announce($version, $profile);

        return $profile;
    }

    /**
     * L'installation se déclare — événement et entrée d'audit (§6.3 point 4).
     *
     * **Après le lock, et sous garde.** Le lock est ce qui rend l'installation
     * vraie ; une trace qui échoue ne doit ni la défaire, ni annoncer un échec
     * à quelqu'un dont le site vient d'être installé. C'est exactement le
     * défaut trouvé en recette le 2 septembre 2026 sur une autre surface
     * (n° 226) : un message d'erreur au moment le plus triomphal coûte plus
     * cher que la trace qu'il prétend défendre. L'échec part au journal
     * d'exceptions, jamais à l'écran.
     *
     * **Aucun module ne peut entendre cet événement dans la requête qui
     * l'émet** : ils ne sont pas amorcés, la table `modules` n'existant pas
     * encore au démarrage de cette requête-là. Le hook vaut pour le Core et
     * pour tout ce qui écoute au démarrage suivant (suivi n° 229).
     */
    private function announce(string $version, HostingProfile $profile): void
    {
        try {
            /*
             * Ce que la télémétrie a le droit de connaître, et rien d'autre
             * (§8 point 2) : version du CMS, version de PHP, pilote de base,
             * type d'installation, capacités détectées. Aucun identifiant,
             * aucune URL, aucun contenu, aucun nom. La charge est donc la même
             * pour l'audit et pour le hook — écrire deux formes ouvrirait la
             * porte à ce que la seconde en dise plus que la première.
             */
            $payload = [
                'version' => $version,
                'php' => PHP_VERSION,
                'database' => (string) config('database.default'),
                'mode' => app()->runningInConsole() ? 'cli' : 'web',
                'telemetry' => TelemetrySetting::isEnabled(),
                'profile' => $profile->toArray(),
            ];

            // Pas de sujet : une installation n'appartient à aucun modèle, et
            // son acteur n'est connecté nulle part — `actor_id` est nullable
            // pour cette raison exacte.
            $this->audit->record('install.completed', null, $payload);

            Hook::action('baobab.installed', $payload);
        } catch (Throwable $e) {
            report($e);
        }
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
            // `withoutSymlink()` plutôt qu'une reconstruction champ par champ :
            // celle-ci **oubliait `argon2id`**, ajouté au profil après elle, si
            // bien qu'un hébergement sans symlink enregistrait « hachage
            // inconnu » dans son lock alors qu'on venait de le constater
            // (trouvé en instruisant la C3b, n° 235).
            return $profile->withoutSymlink();
        }

        return $profile;
    }

    /**
     * Mise en cache de la configuration, des routes et des vues (§4 étape 6).
     *
     * **Publique parce que le wizard la diffère.** `config:cache` reconstruit
     * toute la configuration : l'exécuter au milieu d'une requête HTTP fait
     * perdre les réglages posés à chaud — dont le débranchement des pilotes de
     * session et de cache pendant l'installation — et la réponse en cours se
     * termine sur une configuration qui n'est plus celle qui l'a produite.
     * Constaté en recette le 28 août 2026 : la finalisation aboutissait, le
     * site fonctionnait, mais le navigateur recevait une réponse illisible.
     *
     * La console peut l'appeler en ligne — elle n'a pas de réponse à rendre.
     * Le wizard l'appelle après avoir répondu, sur `terminating()`.
     */
    public function optimize(): void
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
     *
     * **Les deux chemins étaient faux, et le geste ne détruisait donc rien**
     * (trouvé en instruisant la C3, suivi n° 229). Les assets vivent sous
     * `public/baobab/install/` depuis l'amendement du 26 août 2026 (n° 223) —
     * `public/install/` éclipserait la route `/install` — et ce code, écrit
     * avant, supprimait un répertoire qui n'existe pas. Quant au jeton, son
     * chemin est configurable (`baobab.install.state_path`) : le recopier ici
     * laissait le jeton en place sur toute instance qui l'avait déplacé. Il
     * est désormais demandé à son porteur, qui seul sait où il est.
     */
    private function removeInstallerSurface(): void
    {
        $this->files->deleteDirectory(public_path('baobab/install'));
        $this->token->forget();
    }
}
