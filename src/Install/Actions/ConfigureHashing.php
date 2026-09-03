<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\Capability;
use Baobab\Install\EnvFile;
use Illuminate\Contracts\Config\Repository;

/**
 * Choisit l'algorithme de hachage des mots de passe (spec 15 §4.1, n° 220).
 *
 * **Argon2id par défaut, bcrypt en repli annoncé.** Argon2id demande un PHP
 * compilé avec libargon2, ce qui manque encore sur une part du mutualisé :
 * l'exiger refuserait l'installation à qui n'a pas la main sur les options PHP
 * de son hébergeur, sur un algorithme — bcrypt — que Laravel juge sûr. C'est
 * donc une capacité qu'on détecte et à laquelle on s'adapte, jamais un
 * nivellement : le repli est **dit** dans la checklist de fin (§7).
 *
 * **Appelée avant la création du compte administrateur, et c'est le fond de
 * cette Action.** Ce compte naît à l'étape 5 ; poser le driver à l'étape 6,
 * avec les autres réglages de site, donnerait un premier compte haché en
 * bcrypt sur un site en argon2id — un seul compte discordant, celui du Super
 * Admin, et personne pour s'en apercevoir.
 *
 * Écrire `.env` ne suffit pas non plus : le fichier est lu au démarrage du
 * processus, donc l'y écrire ne change rien pour le processus qui l'écrit.
 * La configuration vivante est basculée du même geste — même leçon que la
 * connexion de base au n° 217.
 *
 * Aucun `config/hashing.php` n'est publié : le défaut du framework lit déjà
 * `env('HASH_DRIVER', 'bcrypt')`. Une ligne dans `.env` suffit, et c'est la
 * réponse Laravel-first.
 */
final class ConfigureHashing
{
    public const ARGON = 'argon2id';

    public const BCRYPT = 'bcrypt';

    public function __construct(private readonly Repository $config) {}

    /**
     * @return string le driver réellement retenu
     */
    public function __invoke(EnvFile $env, Capability $argon2id): string
    {
        $driver = $argon2id->isPresent() ? self::ARGON : self::BCRYPT;

        $env->set(['HASH_DRIVER' => $driver]);
        $this->config->set('hashing.driver', $driver);

        return $driver;
    }
}
