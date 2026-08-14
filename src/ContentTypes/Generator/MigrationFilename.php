<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Generator;

/**
 * Nom de fichier d'une migration **de création de table** dans un module généré.
 *
 * Une table n'a qu'une migration de création : elle a une identité logique
 * (`create_{table}_table`), pas seulement un horodatage. Générer un nom neuf à
 * chaque régénération en laissait donc une copie de plus sur disque, que la
 * base ne connaissait pas — et l'installation suivante rejouait un
 * `CREATE TABLE` sur une table déjà là. Défaut relevé le 10 août 2026 en
 * vérifiant la Pass A du M8 point 9 : quatre fichiers pour une seule table
 * après trois régénérations.
 *
 * On réutilise donc le fichier déjà présent quand il y en a un, ce qui rend la
 * régénération idempotente et redonne son rôle au moteur de checksums : le
 * même chemin étant réécrit, une modification manuelle redevient un conflit
 * détectable plutôt qu'un doublon silencieux.
 *
 * **Ne s'applique qu'aux migrations de création.** Une migration d'évolution
 * (`EvolutionMigrationGenerator`) est à chaque fois une migration réellement
 * nouvelle, incrémentale : réutiliser son nom réécrirait une migration déjà
 * jouée, ce que la règle « jamais de modification d'une migration publiée »
 * interdit.
 */
final class MigrationFilename
{
    private const DIRECTORY = 'database/migrations';

    /**
     * Préfixe synthétique, sur le patron du squelette de Laravel lui-même
     * (`0001_01_01_000000_create_users_table.php`) : la date d'un nom de
     * migration n'a jamais porté autre chose qu'un ordre.
     */
    private const PREFIX = '0001_01_01_';

    /**
     * @param  string  $moduleDir  Racine du module sur disque (peut ne pas encore exister).
     * @param  int|null  $rank  Rang de la table dans l'ordre de création (`MigrationOrder`).
     *                          `null` conserve l'ancien nommage horodaté, pour les appelants
     *                          qui n'ont pas de graphe à leur disposition.
     * @return string Chemin relatif au module.
     */
    public static function create(string $moduleDir, string $table, ?int $rank = null): string
    {
        $suffix = "_create_{$table}_table.php";

        // Un module déjà présent sur disque garde ses noms : ils sont ceux que
        // la table `migrations` de cette installation connaît, et les renommer
        // ferait rejouer des migrations déjà exécutées (suivi n° 116, n° 120).
        $existing = self::existingFile(rtrim($moduleDir, '/\\').'/'.self::DIRECTORY, $suffix);

        if ($existing !== null) {
            return self::DIRECTORY.'/'.$existing;
        }

        if ($rank === null) {
            return self::DIRECTORY.'/'.MigrationTimestamp::generate().$suffix;
        }

        $ordinal = str_pad((string) $rank, 6, '0', STR_PAD_LEFT);

        return self::DIRECTORY.'/'.self::PREFIX.$ordinal.'_'.self::moduleToken($moduleDir).$suffix;
    }

    /**
     * Un discriminant stable, dérivé du dossier du module.
     *
     * **Sans lui, deux modules ne peuvent pas posséder une table du même nom.**
     * Laravel enregistre une migration dans la table `migrations` sous son seul
     * nom de fichier, sans le chemin d'où elle vient : deux modules ayant
     * chacun une table `cars` produiraient le même nom, et le second serait
     * considéré comme déjà exécuté — sa table ne serait jamais créée. Défaut
     * introduit puis rattrapé en écrivant le n° 120, et attrapé par deux tests
     * existants qui installent `garage/fleet` et `garage/showroom` dans le même
     * processus.
     *
     * Le jeton occupe la place qu'occupait le suffixe aléatoire de
     * `MigrationTimestamp`, mais il est **déterminé par le module** : même
     * module, même jeton, sur n'importe quelle machine. Le condensat n'a aucun
     * rôle de sécurité, seulement de distinction — le dossier du module est
     * lui-même dérivé de son nom `vendor/slug`, donc unique.
     */
    private static function moduleToken(string $moduleDir): string
    {
        $basename = basename(rtrim(str_replace('\\', '/', $moduleDir), '/'));

        return substr(md5($basename), 0, 6);
    }

    /**
     * En cas de doublons déjà présents (modules régénérés avant le correctif),
     * on garde le plus ancien : c'est celui que la table `migrations` connaît,
     * puisque c'est lui qui a été joué à la première installation.
     */
    private static function existingFile(string $directory, string $suffix): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return null;
        }

        $matching = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => str_ends_with($entry, $suffix),
        ));

        sort($matching);

        return $matching[0] ?? null;
    }
}
