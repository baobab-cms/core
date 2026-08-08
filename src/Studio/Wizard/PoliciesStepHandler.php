<?php

declare(strict_types=1);

namespace Baobab\Studio\Wizard;

use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Support\BlueprintPermissions;

/**
 * Étape 5 — Policies (spec-modules §5.2 étape 5).
 *
 * **Étape sans saisie** : les policies ne se déclarent pas, elles se dérivent
 * du mapping permission → méthode (`BlueprintPermissions::policyMethods()`,
 * la source même dont `ModuleGenerator::writePolicy()` se sert). L'écran
 * n'existe donc que pour montrer, avant génération, la classe qui sera écrite
 * et ce que chacune de ses méthodes exigera — « éditables ensuite » au sens de
 * la spec veut dire dans l'IDE, sur le fichier généré (protégé par checksum),
 * pas dans le wizard.
 *
 * `rules()` vide et `fill()` neutre sont assumés : la soumission ne sert qu'à
 * franchir l'étape, `SaveStudioWizardStep` fait avancer `current_step` et
 * journalise le passage comme pour n'importe quelle autre.
 */
final class PoliciesStepHandler implements StudioStepHandler
{
    public function number(): int
    {
        return 5;
    }

    public function label(): string
    {
        return __('baobab::admin.studio.steps.policies');
    }

    public function view(): string
    {
        return 'baobab::admin.studio.steps.policies';
    }

    public function rules(ModuleBlueprintDraft $draft): array
    {
        return [];
    }

    public function fill(array $validated, array $blueprint): array
    {
        return $blueprint;
    }

    public function initialValues(array $blueprint): array
    {
        return [];
    }

    /**
     * Une entrée par entité : la classe qui sera écrite et son mapping
     * complet. `autoCrudDisabled` porte une conséquence réelle et non
     * évidente du générateur — la policy mappe **toujours** les cinq méthodes
     * CRUD, y compris quand l'étape 3 a coupé `auto_crud` : les chaînes
     * existent alors dans la policy sans exister dans le manifeste, donc
     * personne ne peut les détenir. Mieux vaut le dire ici que le laisser
     * découvrir après installation.
     */
    public function viewData(array $blueprint): array
    {
        $slug = BlueprintPermissions::slug($blueprint);
        $custom = $blueprint['permissions']['custom'] ?? [];

        $policies = [];

        foreach ($blueprint['entities'] ?? [] as $entity) {
            $key = (string) ($entity['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $policies[] = [
                'entity' => $key,
                'file' => "src/Policies/{$key}Policy.php",
                'methods' => BlueprintPermissions::policyMethods($slug, $key, $custom),
            ];
        }

        return [
            'policies' => $policies,
            'autoCrudDisabled' => ($blueprint['permissions']['auto_crud'] ?? true) === false,
        ];
    }

    public function valuesFromOldInput(array $old, array $values): array
    {
        return $values;
    }
}
