<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\DatabaseCredentials;
use Baobab\Install\DatabaseInspection;
use Baobab\Install\EnvFile;
use Baobab\Install\Exceptions\InstallationStepFailed;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Étape 2 de l'installation (spec 15 §4) : connexion éprouvée, puis écrite.
 *
 * **L'ordre est le fond de cette Action.** La connexion est testée pour de
 * vrai *avant* que quoi que ce soit ne touche `.env` : écrire d'abord et
 * vérifier ensuite laisserait, à la moindre faute de frappe dans un mot de
 * passe, une application qui ne démarre plus — et l'installateur avec elle,
 * puisqu'il tourne dedans. C'est le genre de panne dont on ne sort pas sans
 * accès aux fichiers, c'est-à-dire précisément ce que le public visé n'a pas.
 *
 * Une base **non vide n'arrête rien** : elle est constatée, et le préfixe est
 * la réponse (§4 étape 2, suivi n° 214). Un mutualisé ne donne souvent qu'une
 * seule base, et la refuser reviendrait à refuser la cible.
 */
final class ConfigureDatabase
{
    /** Connexion jetable, le temps du test : la connexion par défaut de l'application ne doit pas bouger. */
    private const PROBE = 'baobab_install_probe';

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Repository $config,
    ) {}

    /** Tables dont la présence prouve qu'un Baobab vit déjà dans cette base. */
    private const BAOBAB_TABLES = ['modules', 'content_types'];

    public function __invoke(DatabaseCredentials $credentials, EnvFile $env, string $appEnv = 'production'): DatabaseInspection
    {
        $this->refuseSqliteInProduction($credentials, $appEnv);

        $inspection = $this->probe($credentials);

        $this->refuseExistingInstallation($inspection, $credentials->prefix);

        $env->set($credentials->toEnv());

        $this->useAsDefault($credentials);

        return $inspection;
    }

    /**
     * Fait de la base éprouvée la connexion par défaut du processus.
     *
     * **Sans cela, tout ce qui suit s'exécute sur la mauvaise base**, et le
     * défaut est invisible : `.env` est lu au démarrage du processus, si bien
     * qu'y écrire de nouveaux identifiants ne change rien pour le processus
     * qui les écrit. Les migrations partiraient donc sur la connexion chargée
     * au boot — la base de développement de qui lance la commande, ou rien du
     * tout sur une archive fraîche.
     *
     * Trouvé sur une question de l'utilisateur avant toute recette, et non par
     * les tests : ceux-ci vérifiaient qu'un lock et un compte existaient, sans
     * jamais demander **sur quelle base** ils avaient atterri.
     */
    private function useAsDefault(DatabaseCredentials $credentials): void
    {
        $connection = $credentials->driver;

        $this->config->set('database.connections.'.$connection, $credentials->toConnectionConfig());
        $this->config->set('database.default', $connection);

        $this->db->purge($connection);
        $this->db->setDefaultConnection($connection);
    }

    /**
     * Refuse d'installer par-dessus un Baobab qui vit déjà là.
     *
     * La sentinelle du §3 ne suffit pas : `storage/` est couramment exclu des
     * sauvegardes, ou vidé lors d'une migration d'hébergement. Un lock perdu
     * ferait croire à l'installateur qu'il est sur un terrain neuf, et il
     * migrerait par-dessus un site vivant — la base, elle, ne ment pas.
     */
    private function refuseExistingInstallation(DatabaseInspection $inspection, string $prefix): void
    {
        foreach (self::BAOBAB_TABLES as $table) {
            if (! in_array($prefix.$table, $inspection->tables, true)) {
                continue;
            }

            throw InstallationStepFailed::database(
                'Cette base contient déjà un site Baobab (table « '.$prefix.$table.' »). '
                .'L\'installation s\'arrête ici : rien n\'a été modifié. '
                .'Utilisez une autre base, un préfixe de tables différent, ou restaurez le fichier '
                .'storage/app/baobab/installed.lock si vous cherchiez à reprendre la main sur ce site.',
            );
        }
    }

    /**
     * Spec 15 §8, décision 3 — SQLite est interdit en production.
     *
     * L'interdit se joue ici et pas seulement dans le sélecteur du wizard :
     * un installateur non interactif (§5) ne passe par aucun sélecteur.
     */
    private function refuseSqliteInProduction(DatabaseCredentials $credentials, string $appEnv): void
    {
        if ($credentials->driver === 'sqlite' && ! in_array($appEnv, ['local', 'staging'], true)) {
            throw InstallationStepFailed::database(
                'SQLite ne convient pas à un site en production : choisissez MySQL, MariaDB ou PostgreSQL. '
                .'SQLite reste disponible en environnement local ou de préproduction.',
            );
        }
    }

    private function probe(DatabaseCredentials $credentials): DatabaseInspection
    {
        $this->config->set('database.connections.'.self::PROBE, $credentials->toConnectionConfig());
        $this->db->purge(self::PROBE);

        try {
            $connection = $this->db->connection(self::PROBE);
            $connection->getPdo();

            // **Le schéma est passé explicitement, et ce n est pas un détail.**
            // Sans lui, MySQL rend les tables de TOUTES les bases visibles par
            // le compte : une base neuve paraît alors pleine de tables qui
            // appartiennent au voisin, et la garde d installation refuse une
            // base parfaitement vide. Mesuré : 230 tables rendues au lieu de 0.
            // Invisible en SQLite, qui n a qu un schéma — donc invisible à la
            // suite du paquet, qui tourne sur SQLite.
            //
            // `getCurrentSchemaName()` et non `getDatabaseName()` : en SQLite le
            // second rend un chemin de fichier, qui ne désigne aucun schéma et
            // filtrerait tout. Le premier rend `main` là et le nom de la base
            // sur MySQL — la seule forme qui vaille pour les deux.
            $builder = $connection->getSchemaBuilder();

            $tables = array_map(
                static fn (array $table): string => $table['name'],
                $builder->getTables($builder->getCurrentSchemaName()),
            );
        } catch (Throwable $e) {
            throw InstallationStepFailed::database(
                'La connexion à la base de données a échoué. Vérifiez le nom de la base, '
                .'l\'identifiant et le mot de passe fournis par votre hébergement, '
                .'puis réessayez — rien n\'a été enregistré.',
                $e,
            );
        } finally {
            $this->db->purge(self::PROBE);
        }

        return new DatabaseInspection($tables, $credentials->prefix);
    }
}
