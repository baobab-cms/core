<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Editorial\Support;

/**
 * Diff champ par champ entre deux snapshots de révision (spec 09 §6) : diff
 * mots pour les paires de chaînes (LCS maison — pas de dépendance ajoutée),
 * avant/après du bloc pour tout le reste (relations, médias, repeater/json —
 * décision v1 explicite de la spec, §10 décision 2).
 */
final class RevisionDiffer
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: mixed, after: mixed, changed: bool, words: ?list<array{type: string, text: string}>}>
     */
    public function diff(array $before, array $after): array
    {
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        $result = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;
            $changed = $beforeValue !== $afterValue;

            $result[$key] = [
                'before' => $beforeValue,
                'after' => $afterValue,
                'changed' => $changed,
                'words' => $changed && is_string($beforeValue) && is_string($afterValue)
                    ? $this->wordDiff($beforeValue, $afterValue)
                    : null,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function wordDiff(string $before, string $after): array
    {
        $beforeTokens = $this->tokenize($before);
        $afterTokens = $this->tokenize($after);

        $lcs = $this->longestCommonSubsequence($beforeTokens, $afterTokens);

        $tokens = [];
        $i = 0;
        $j = 0;

        foreach ($lcs as [$beforeIndex, $afterIndex]) {
            while ($i < $beforeIndex) {
                $tokens[] = ['type' => 'delete', 'text' => $beforeTokens[$i]];
                $i++;
            }

            while ($j < $afterIndex) {
                $tokens[] = ['type' => 'insert', 'text' => $afterTokens[$j]];
                $j++;
            }

            $tokens[] = ['type' => 'equal', 'text' => $beforeTokens[$beforeIndex]];
            $i++;
            $j++;
        }

        while ($i < count($beforeTokens)) {
            $tokens[] = ['type' => 'delete', 'text' => $beforeTokens[$i]];
            $i++;
        }

        while ($j < count($afterTokens)) {
            $tokens[] = ['type' => 'insert', 'text' => $afterTokens[$j]];
            $j++;
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : $tokens;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: int, 1: int}> Paires d'indices (a, b) des tokens communs, dans l'ordre.
     */
    private function longestCommonSubsequence(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $table = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $pairs = [];
        $i = 0;
        $j = 0;

        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $pairs[] = [$i, $j];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $pairs;
    }
}
