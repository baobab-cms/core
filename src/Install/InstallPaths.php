<?php

declare(strict_types=1);

namespace Baobab\Install;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;

/**
 * Les chemins que l'installation regarde — définis **une seule fois**.
 *
 * La console les assemblait dans `InstallCommand`. Le wizard en a besoin des
 * mêmes, et les recopier aurait laissé les deux surfaces diverger en silence
 * sur ce qui compte : si la console exige `bootstrap/cache` accessible en
 * écriture et que le navigateur l'oublie, les deux n'ont plus la même
 * définition d'« installable », et c'est la recette d'un site installé
 * graphiquement qui tombe au premier cache.
 *
 * Même raison que le pipeline partagé (§1, n° 216), à une échelle plus
 * modeste : ce sont des adaptateurs, pas deux produits.
 */
final class InstallPaths
{
    public static function env(Filesystem $files): EnvFile
    {
        /** @var string $path */
        $path = config('baobab.install.env_path', base_path('.env'));

        return new EnvFile($files, $path);
    }

    /**
     * Débranche de la base tout ce qui n'a pas besoin d'elle, tant que le site
     * n'est pas installé — suivi n° 224.
     *
     * **Ce n'est pas une commodité, c'est la seule configuration cohérente.**
     * Les deux `.env.example` livrent `SESSION_DRIVER`, `CACHE_STORE` **et**
     * `QUEUE_CONNECTION` à `database` — les défauts de Laravel. Or à
     * l'installation il n'y a **aucune base** : c'est l'utilisateur qui la
     * désigne, à l'écran suivant. Aucun des trois ne peut fonctionner, par
     * définition.
     *
     * **Deux recettes navigateur successives, deux tiers du problème.** La
     * première a montré la session : elle porte le verrou exclusif et le
     * brouillon, donc les identifiants déjà saisis, et se perdait au premier
     * écran. La seconde a montré le cache — et celle-là abattait la porte
     * elle-même, le throttling du §6.2 passant par le `RateLimiter`, qui passe
     * par le cache. La file d'attente est ajoutée par le même raisonnement
     * plutôt que par une troisième recette : `sync` exécute sur place, ce qui
     * est de toute façon le seul mode possible sans travailleur.
     *
     * *Enseignement : le défaut n'était pas dans un sous-système mais dans une
     * hypothèse — « une application Laravel a une base ». L'installateur est
     * précisément le moment où elle est fausse.*
     *
     * Hors du provider pour être éprouvable : la logique tient en quelques
     * lignes, mais elle décide de la survie du parcours entier.
     */
    public static function useLocalDrivers(Repository $config, Filesystem $files): void
    {
        if ($config->get('session.driver') !== 'file') {
            $path = $config->get('session.files', storage_path('framework/sessions'));

            // Une archive fraîchement décompressée peut avoir perdu ses
            // répertoires vides en chemin : on ne suppose pas qu'il existe.
            if (is_string($path)) {
                $files->ensureDirectoryExists($path);
            }

            $config->set('session.driver', 'file');
        }

        if ($config->get('cache.default') === 'database') {
            $config->set('cache.default', 'file');
        }

        if ($config->get('queue.default') === 'database') {
            $config->set('queue.default', 'sync');
        }
    }

    /**
     * Garantit un `.env` et une `APP_KEY` avant que la moindre page ne s'ouvre.
     *
     * **Sans elle, l'archive ne peut pas afficher son propre installateur.**
     * Le zip ne contient pas de `.env` — c'est voulu — et son `.env.example`
     * livre `APP_KEY=` vide. Or `/install` passe par le groupe `web`, donc par
     * `EncryptCookies`, qui exige une clé : Laravel lève `MissingAppKeyException`
     * **avant** d'atteindre le moindre contrôleur. Constaté le 28 août 2026 sur
     * une archive réellement décompressée.
     *
     * La spec 15 §4 prévoit bien « `.env` créé depuis `.env.example`, `APP_KEY`
     * générée » — mais à l'**étape 2**, c'est-à-dire une fois le formulaire de
     * base de données rempli. Beaucoup trop tard : on n'atteint jamais le
     * premier écran. Cette garantie doit donc précéder le routage, pas figurer
     * dans la séquence.
     *
     * *C'est le troisième défaut de la même famille : une hypothèse d'« application
     * déjà installée » qui ne tient pas au moment de l'installer. Les deux
     * premiers étaient les pilotes de session, de cache et de file.*
     *
     * La clé écrite est **définitive** : elle chiffre les sessions et les
     * cookies, et l'installateur ne doit surtout pas en générer une nouvelle à
     * chaque requête — la session de l'installateur, qui porte le verrou et le
     * brouillon, deviendrait illisible d'une page à l'autre.
     */
    public static function ensureApplicationKey(Repository $config, Filesystem $files): void
    {
        $key = $config->get('app.key');

        if (is_string($key) && $key !== '') {
            return;
        }

        $env = self::env($files);
        $env->createFromExample(base_path('.env.example'));

        $existing = $env->exists() ? $env->get('APP_KEY') : null;

        if (! is_string($existing) || $existing === '') {
            $existing = 'base64:'.base64_encode(random_bytes(32));
            $env->set(['APP_KEY' => $existing]);
        }

        // Le fichier vient d'être écrit, mais la configuration a été résolue au
        // démarrage : sans cette ligne, la requête en cours n'aurait toujours
        // pas de clé et échouerait comme avant.
        $config->set('app.key', $existing);
    }

    /**
     * Empreinte des assets du wizard, pour casser le cache du navigateur.
     *
     * **Pas la version du CMS**, comme je l'avais d'abord écrit : elle vaut
     * `dev` sur un poste de développement et ne change donc jamais. Résultat,
     * un navigateur gardait une feuille périmée et l'écran de progression
     * s'affichait sans style — trouvé en recette le 28 août 2026, et le second
     * incident de ce genre après celui des assets non republiés.
     *
     * La date de modification du fichier change à chaque republication, ce qui
     * est exactement la question posée. Repli sur la version du CMS si le
     * fichier est absent : mieux vaut un cache trop long qu'une page qui
     * n'ouvre pas.
     */
    public static function assetVersion(): string
    {
        $published = public_path('baobab/install/wizard.css');

        if (is_file($published)) {
            return (string) filemtime($published);
        }

        /** @var string $version */
        $version = config('baobab.version', 'dev');

        return $version;
    }

    /**
     * Répertoires dont l'installation a besoin en écriture — libellé => chemin.
     *
     * Le libellé est ce que l'utilisateur lira s'il faut corriger des droits :
     * il doit désigner un chemin qu'il reconnaîtra dans son client FTP, pas un
     * chemin absolu de serveur.
     *
     * @return array<string, string>
     */
    public static function writable(): array
    {
        return [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'public' => public_path(),
        ];
    }
}
