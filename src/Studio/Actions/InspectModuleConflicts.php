<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Generator\ModuleGenerator;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Support\LineDiff;

/**
 * Fichiers d'un module déjà généré qui seraient **écrasés** par une
 * régénération, accompagnés de leur diff (spec 01 §5.4).
 *
 * Action de lecture pure, distincte de `GenerateModuleFromDraft` : elle
 * répond à « que perdrais-je ? » sans rien écrire, ce qui est exactement ce
 * qu'un écran de confirmation doit pouvoir demander. Vide tant que le module
 * n'a jamais été généré, ou tant qu'aucun fichier généré n'a été modifié à la
 * main.
 */
final class InspectModuleConflicts
{
    public function __construct(private readonly ModuleGenerator $generator) {}

    /**
     * @return array<string, array{diff: list<array{type: string, line: string, marker: string}>|null}>
     *                                                                                                  `diff` vaut `null` quand le fichier dépasse la taille comparable
     *                                                                                                  (`LineDiff::MAX_LINES`) : l'écran le dit plutôt que de faire ramer la requête.
     */
    public function __invoke(ModuleBlueprintDraft $draft): array
    {
        $blueprint = ModuleBlueprint::fromJson((string) json_encode($draft->migratedBlueprint()));

        $conflicts = [];

        foreach ($this->generator->conflicts($blueprint) as $relativePath => $versions) {
            $conflicts[$relativePath] = [
                'diff' => $this->withMarkers(LineDiff::compare($versions['disk'], $versions['generated'])),
            ];
        }

        return $conflicts;
    }

    /**
     * Le marqueur de gauche est calculé ici, pas dans la vue : une vue admin
     * ne porte aucune logique, pas même un `match` d'affichage.
     *
     * @param  list<array{type: string, line: string}>|null  $diff
     * @return list<array{type: string, line: string, marker: string}>|null
     */
    private function withMarkers(?array $diff): ?array
    {
        if ($diff === null) {
            return null;
        }

        return array_map(static fn (array $line): array => [
            ...$line,
            'marker' => match ($line['type']) {
                'removed' => '-',
                'added' => '+',
                default => ' ',
            },
        ], $diff);
    }
}
