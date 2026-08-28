<?php

declare(strict_types=1);

namespace Baobab\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Jeton d'installation — spec 15 §6.2.
 *
 * **Le problème qu'il résout.** Une installation ouverte est une porte ouverte :
 * entre le moment où les fichiers arrivent sur le serveur et celui où le site
 * est installé, `/install` répond à qui la demande. Sans garde, le premier
 * visiteur venu installe « votre » site — et devient son Super Admin.
 *
 * **La réponse.** Un code que seule une personne ayant accès aux fichiers peut
 * lire : il est écrit dans `storage/app/baobab/install-token.txt` et **jamais
 * rendu au navigateur**. Le mode CLI l'affiche en console, l'archive le laisse
 * sur le disque. Le navigateur, lui, ne peut que le proposer et se faire dire
 * oui ou non.
 *
 * **Il naît au premier accès, pas à l'installation du paquet** : un jeton créé
 * trop tôt serait un secret qui traîne, et un jeton créé à chaque requête ne
 * serait pas un secret du tout.
 *
 * La comparaison passe par `hash_equals()` : comparer deux chaînes avec `===`
 * s'interrompt au premier caractère différent, et la durée de l'échec renseigne
 * alors sur le préfixe correct. Le gain est théorique sur un réseau réel, le
 * coût d'y penser est nul, et l'inverse ne s'explique pas en revue.
 */
final class InstallToken
{
    /**
     * 32 caractères hexadécimaux, soit 128 bits — ni devinable, ni pénible à
     * recopier depuis une console ou un client FTP. Les jetons plus longs se
     * recopient mal, et c'est un vrai motif d'abandon.
     */
    private const BYTES = 16;

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
    ) {}

    /**
     * Rend le jeton courant, en le créant au premier appel.
     *
     * Le répertoire est créé au passage : `storage/app/baobab/` existe sur une
     * installation normale, mais pas nécessairement sur une archive fraîchement
     * décompressée dont les répertoires vides ont pu être perdus en chemin.
     */
    public function value(): string
    {
        if ($this->files->isFile($this->path)) {
            $existing = trim((string) $this->files->get($this->path));

            if ($existing !== '') {
                return $existing;
            }
        }

        $token = bin2hex(random_bytes(self::BYTES));

        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, $token.PHP_EOL);

        return $token;
    }

    public function matches(string $candidate): bool
    {
        return hash_equals($this->value(), trim($candidate));
    }

    /**
     * Efface le jeton — appelé à l'autodestruction (§6.3, Pass C3).
     *
     * Ne rend pas d'erreur si le fichier n'existe plus : l'autodestruction doit
     * pouvoir être rejouée sans conséquence.
     */
    public function forget(): void
    {
        if ($this->files->isFile($this->path)) {
            $this->files->delete($this->path);
        }
    }

    public function path(): string
    {
        return $this->path;
    }
}
