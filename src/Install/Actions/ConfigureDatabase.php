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

    public function __invoke(DatabaseCredentials $credentials, EnvFile $env, string $appEnv = 'production'): DatabaseInspection
    {
        $this->refuseSqliteInProduction($credentials, $appEnv);

        $inspection = $this->probe($credentials);

        $env->set($credentials->toEnv());

        return $inspection;
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

            $tables = array_map(
                static fn (array $table): string => $table['name'],
                $connection->getSchemaBuilder()->getTables(),
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
