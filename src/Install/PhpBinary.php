<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Le chemin de l'interpréteur PHP en ligne de commande — et le degré de
 * certitude qu'on en a (spec 15 §7, arbitrage A5 du n° 229, B2 du n° 235).
 *
 * **Les deux entrées cron du §7 en dépendent entièrement.** Un cron de panneau
 * d'hébergement ne dispose pas du `PATH` d'un shell interactif : `php artisan`
 * y échoue en silence, et il faut donc un chemin absolu.
 *
 * **La certitude n'est pas un détail de présentation.** Depuis la console,
 * `PHP_BINARY` *est* le binaire qui exécute la commande : le chemin est
 * constaté. Depuis une requête HTTP, la même constante désigne le binaire
 * **FPM ou CGI**, qui n'est pas celui de la ligne de commande — souvent dans
 * le même répertoire, parfois ailleurs, et sur certains mutualisés il n'existe
 * même pas de binaire CLI accessible. Rendre ce chemin-là sans réserve
 * produirait une ligne de cron fausse **qui échoue sans bruit**, ce qui est
 * exactement le mode de panne que la checklist doit prévenir.
 *
 * D'où le tri-état de la Pass A1 appliqué à un chemin : on propose, on n'affirme
 * pas, et l'écran dit où confirmer.
 */
final readonly class PhpBinary
{
    private function __construct(
        public string $path,
        /** Vrai quand le chemin a été **constaté**, faux quand il est déduit. */
        public bool $confirmed,
    ) {}

    /**
     * Le chemin relu d'un lock.
     *
     * **Jamais confirmé** : le lock retient le chemin, pas les circonstances
     * dans lesquelles il a été obtenu. Le supposer constaté ferait dire à un
     * ancien lock plus qu'il ne sait. Cette forme ne sert qu'à comparer des
     * dérives ; la checklist, elle, redétecte à chaque fois.
     */
    public static function remembered(string $path): self
    {
        return new self($path, false);
    }

    /**
     * Détecte le binaire CLI, ou rend `null` si l'on n'a rien d'honnête à
     * proposer.
     *
     * @param  string|null  $sapi  `PHP_SAPI` par défaut — injectable pour les tests
     */
    public static function detect(?string $sapi = null, ?string $binary = null, ?string $binDir = null): ?self
    {
        $sapi ??= PHP_SAPI;
        $binary ??= PHP_BINARY;
        $binDir ??= PHP_BINDIR;

        if ($sapi === 'cli' || $sapi === 'phpdbg') {
            return $binary === '' ? null : new self($binary, true);
        }

        /*
         * Depuis le web, on cherche un binaire CLI **à côté** de celui qui
         * tourne : `PHP_BINDIR` désigne le répertoire des exécutables de
         * l'installation PHP courante, et `php` y est la convention.
         *
         * On vérifie qu'il existe plutôt que de l'affirmer : proposer un
         * chemin inexistant serait pire que de n'en proposer aucun, puisque
         * l'utilisateur le collerait dans son panneau sans moyen de le tester.
         *
         * `php.exe` est essayé aussi. Non que l'on installe des sites Windows
         * — mais la suite de tests, elle, y tourne, et une détection qui ne
         * vaut que sur la machine d'en face est une détection verte en CI et
         * fausse en local. Le coût est un `is_file()` de plus.
         */
        $dir = rtrim($binDir, '/\\');

        foreach (['/php', '/php.exe'] as $name) {
            if (is_file($dir.$name)) {
                return new self($dir.$name, false);
            }
        }

        return null;
    }
}
