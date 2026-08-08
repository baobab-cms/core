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

    /**
     * Données de référence propres à l'étape, poussées telles quelles à la vue
     * (listes de choix, catalogues, aperçus dérivés…). Calculées côté serveur
     * pour que la vue n'ait aucune logique — cf. « pas de `@php` dans les vues
     * admin ». Vide pour une étape qui n'en a pas besoin.
     *
     * Distinct d'`initialValues()` : ce que retourne `viewData()` n'est jamais
     * une valeur de formulaire et ne se ré-hydrate donc pas depuis l'entrée
     * refusée — c'est ce qui permet à une étape entièrement dérivée (étape 5,
     * Policies) de n'avoir aucune valeur et rien que des données d'affichage.
     *
     * @param  array<string, mixed>  $blueprint  blueprint courant du brouillon (vide à la création)
     * @return array<string, mixed>
     */
    public function viewData(array $blueprint): array;

    /**
     * Ré-hydrate les valeurs de la vue depuis l'entrée re-flashée par
     * `back()->withInput()` quand la cross-validation du blueprint a échoué.
     *
     * Une étape dont la saisie tient dans des champs simples n'a rien à faire
     * ici (`<x-baobab::field.*>` applique déjà `old()` lui-même) : elle
     * retourne `$values` tel quel. Une étape dont la saisie voyage en une
     * chaîne JSON doit la redécoder, sinon le formulaire se réinitialise
     * depuis le blueprint **stocké** — celui d'avant la saisie refusée — et
     * tout le travail en cours est perdu.
     *
     * @param  array<string, mixed>  $old  entrée brute de la requête refusée
     * @param  array<string, mixed>  $values  valeurs issues d'`initialValues()`
     * @return array<string, mixed>
     */
    public function valuesFromOldInput(array $old, array $values): array;
}
