<?php

declare(strict_types=1);

namespace Baobab\Support;

/**
 * Diff ligne à ligne entre deux textes. Écrit pour les fichiers générés du
 * Studio (spec 01 §5.4, « diff proposé en cas de conflit »), il sert aussi à
 * l'écran des e-mails, qui compare deux versions du défaut d'un template
 * (spec 13 §3.2).
 *
 * **Déplacé de `Baobab\Studio\Support` vers ici le 22 août 2026** : comparer
 * deux chaînes ligne à ligne n'appartient à aucun domaine métier, et faire
 * dépendre le domaine Mail du domaine Studio pour un utilitaire de texte
 * aurait été un couplage sans justification.
 *
 * **Pourquoi une implémentation maison plutôt qu'une librairie** :
 * `sebastian/diff` est bien présent dans `vendor/`, mais uniquement comme
 * dépendance **de développement** (tirée par PHPUnit) — s'en servir dans
 * `src/` casserait toute installation de production, où les dev-dependencies
 * sont absentes. Ajouter une dépendance Composer est par ailleurs une décision
 * de spec. Pour un affichage de conflit, une plus longue sous-séquence commune
 * suffit largement : ~60 lignes, testables, sans contrat externe à suivre.
 *
 * L'algorithme est un LCS classique en O(n×m) sur les lignes. C'est
 * volontairement naïf : les fichiers comparés sont des fichiers générés
 * (quelques dizaines à quelques centaines de lignes). Au-delà de
 * `MAX_LINES`, on renonce à comparer et on le dit, plutôt que de faire ramer
 * une requête admin sur une matrice de plusieurs millions de cellules.
 */
final class LineDiff
{
    /** Au-delà, le coût quadratique n'est plus raisonnable dans une requête. */
    public const MAX_LINES = 1500;

    /**
     * @return list<array{type: string, line: string}>|null `null` si l'un des
     *                                                      deux fichiers dépasse `MAX_LINES`.
     */
    public static function compare(string $old, string $new): ?array
    {
        $oldLines = preg_split('/\r\n|\r|\n/', $old) ?: [];
        $newLines = preg_split('/\r\n|\r|\n/', $new) ?: [];

        if (count($oldLines) > self::MAX_LINES || count($newLines) > self::MAX_LINES) {
            return null;
        }

        return self::walk($oldLines, $newLines, self::lcsTable($oldLines, $newLines));
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return array<int, array<int, int>>
     */
    private static function lcsTable(array $old, array $new): array
    {
        $rows = count($old);
        $columns = count($new);

        $table = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $table[$i][$j] = $old[$i] === $new[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }

    /**
     * Parcours avant de la table : produit les lignes dans l'ordre du fichier,
     * ce qu'un affichage de diff attend (une reconstruction arrière obligerait
     * à inverser le résultat).
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @param  array<int, array<int, int>>  $table
     * @return list<array{type: string, line: string}>
     */
    private static function walk(array $old, array $new, array $table): array
    {
        $diff = [];
        $i = 0;
        $j = 0;
        $rows = count($old);
        $columns = count($new);

        while ($i < $rows && $j < $columns) {
            if ($old[$i] === $new[$j]) {
                $diff[] = ['type' => 'kept', 'line' => $old[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $diff[] = ['type' => 'removed', 'line' => $old[$i]];
                $i++;
            } else {
                $diff[] = ['type' => 'added', 'line' => $new[$j]];
                $j++;
            }
        }

        for (; $i < $rows; $i++) {
            $diff[] = ['type' => 'removed', 'line' => $old[$i]];
        }

        for (; $j < $columns; $j++) {
            $diff[] = ['type' => 'added', 'line' => $new[$j]];
        }

        return $diff;
    }
}
