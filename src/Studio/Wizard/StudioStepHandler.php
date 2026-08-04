<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Models\ModuleBlueprintDraft;

/**
 * Contrat d'une étape implémentée du Wizard Studio (spec-modules §5.2).
 * Chaque passe (B1→B4) ajoute un handler à `StudioWizardSteps` sans jamais
 * toucher au shell (`StudioController`/`<x-baobab::wizard>`) — même logique
 * incrémentale que les sous-générateurs de Pass A.
 */
interface StudioStepHandler
{
    /**
     * Position dans le parcours à 9 étapes (spec-modules §5.2) — doit
     * correspondre à l'index (1-based) du libellé dans `StudioWizardSteps::LABELS`.
     */
    public function number(): int;

    public function label(): string;

    /**
     * Nom de la vue Blade du corps de l'étape (ex. `baobab::admin.studio.steps.identity`).
     */
    public function view(): string;

    /**
     * Règles de validation Laravel pour la soumission de cette étape.
     *
     * @return array<string, mixed>
     */
    public function rules(ModuleBlueprintDraft $draft): array;

    /**
     * Fusionne les données validées de cette étape dans le blueprint JSON
     * du brouillon (tableau déjà décodé) et retourne le blueprint complet.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>
     */
    public function fill(array $validated, array $blueprint): array;

    /**
     * Valeurs pré-remplies pour réafficher cette étape depuis un brouillon
     * existant (extrait du blueprint courant, forme brute attendue par la vue).
     *
     * @param  array<string, mixed>  $blueprint
     * @return array<string, mixed>
     */
    public function initialValues(array $blueprint): array;
}
