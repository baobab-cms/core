<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Evolution\ModuleEvolutionMigrationGenerator;
use Baobab\Studio\Evolution\ModuleSchemaReconciler;
use Baobab\Studio\Exceptions\ColumnHasNullsException;
use Baobab\Studio\Exceptions\DestructiveSchemaChangeNotConfirmedException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Illuminate\Support\Facades\Artisan;

/**
 * Aligne la base sur le blueprint d'un module **déjà installé** (spec-modules
 * §5.4, suivi n° 111 — qui porte le n° 137).
 *
 * C'est la moitié manquante de la régénération. `ModuleGenerator` réécrit les
 * fichiers ; jusqu'ici rien ne touchait au schéma, si bien qu'une entité ajoutée
 * après coup voyait sa migration écrite mais jamais jouée, et qu'une colonne
 * fausse depuis l'origine le restait.
 *
 * Deux moitiés, dans cet ordre :
 *
 * 1. **Les colonnes** — un plan par table, transformé en migration
 *    incrémentale. C'est le travail réel : la migration de création a déjà été
 *    jouée et ne se retouche pas.
 * 2. **Les tables** — rien à générer. La migration de création d'une entité
 *    nouvelle est déjà sur disque, et son nom dérivé du graphe (n° 120) est
 *    absent de la table `migrations` : le `migrate` final la joue. Le même appel
 *    joue les migrations incrémentales qu'on vient d'écrire, d'où un seul
 *    passage pour les deux.
 *
 * Deux garde-fous, tous deux vérifiés **avant la première écriture** — patron de
 * `InspectModuleConflicts` (Pass C du point 1) : ne jamais laisser un module à
 * moitié migré.
 *
 * - Supprimer une colonne supprime ses données : refusé sans confirmation
 *   explicite, comme `EvolveContentType` le fait pour un champ retiré.
 * - Rendre un champ obligatoire resserre sa colonne en NOT NULL, ce que la base
 *   refuse si une ligne y porte NULL. La garde le dit avec le nom de la colonne
 *   et le nombre de lignes, au lieu de laisser sortir une `QueryException`.
 */
final class EvolveModuleSchema
{
    public function __construct(
        private readonly ModuleSchemaReconciler $reconciler,
        private readonly ModuleEvolutionMigrationGenerator $migrations,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{migrations: list<string>, created: list<string>, changes: int}
     *
     * @throws DestructiveSchemaChangeNotConfirmedException
     * @throws ColumnHasNullsException
     */
    public function __invoke(ModuleBlueprintDraft $draft, ModuleBlueprint $blueprint, bool $confirmDestructive = false): array
    {
        /** @var array<string, mixed>|null $snapshot */
        $snapshot = $draft->generated_blueprint;

        $plan = $this->reconciler->reconcile($blueprint, $snapshot);

        $this->assertNonDestructive($plan['tables'], $confirmDestructive);
        $this->assertNoBlockingNulls($plan['tables']);

        $moduleDir = $draft->moduleDir();

        $written = [];
        $changes = 0;

        foreach ($plan['tables'] as $tablePlan) {
            $written[] = $this->migrations->generate($tablePlan, $moduleDir);
            $changes += count($tablePlan['added'])
                + count($tablePlan['dropped'])
                + count($tablePlan['renamed'])
                + count($tablePlan['retightened']);
        }

        if ($written !== [] || $plan['pending'] !== []) {
            Artisan::call('migrate', [
                '--path' => $moduleDir.'/database/migrations',
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $result = ['migrations' => $written, 'created' => $plan['pending'], 'changes' => $changes];

        if ($written === [] && $plan['pending'] === []) {
            return $result;
        }

        $this->audit->record('studio.module.schema_evolved', $draft, $result);

        Hook::action('baobab.studio.module.schema_evolved', $draft, $result);

        return $result;
    }

    /**
     * @param  list<array{table: string, dropped: list<array{name: string, definition: string}>}>  $plans
     *
     * @throws DestructiveSchemaChangeNotConfirmedException
     */
    private function assertNonDestructive(array $plans, bool $confirmDestructive): void
    {
        if ($confirmDestructive) {
            return;
        }

        $dropped = [];

        foreach ($plans as $plan) {
            foreach ($plan['dropped'] as $column) {
                $dropped[] = "{$plan['table']}.{$column['name']}";
            }
        }

        if ($dropped !== []) {
            throw DestructiveSchemaChangeNotConfirmedException::forColumns($dropped);
        }
    }

    /**
     * @param  list<array{table: string, retightened: list<array{name: string, nulls: int}>}>  $plans
     *
     * @throws ColumnHasNullsException
     */
    private function assertNoBlockingNulls(array $plans): void
    {
        foreach ($plans as $plan) {
            foreach ($plan['retightened'] as $column) {
                if ($column['nulls'] > 0) {
                    throw ColumnHasNullsException::forColumn($plan['table'], $column['name'], $column['nulls']);
                }
            }
        }
    }
}
