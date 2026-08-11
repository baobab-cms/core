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
     * @param  string  $moduleDir  Racine du module sur disque (peut ne pas encore exister).
     * @return string Chemin relatif au module.
     */
    public static function create(string $moduleDir, string $table): string
    {
        $suffix = "_create_{$table}_table.php";

        $existing = self::existingFile(rtrim($moduleDir, '/\\').'/'.self::DIRECTORY, $suffix);

        if ($existing !== null) {
            return self::DIRECTORY.'/'.$existing;
        }

        return self::DIRECTORY.'/'.MigrationTimestamp::generate().$suffix;
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
