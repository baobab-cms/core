<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Studio\Blueprint\ModuleBlueprint;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Wizard\StudioStepHandler;
use Baobab\Studio\Wizard\StudioWizardSteps;

/**
 * Sauvegarde la soumission d'une étape du wizard dans le blueprint JSON du
 * brouillon (spec-modules §5.4). `current_step` n'avance jamais au-delà de
 * la dernière étape implémentée (`StudioWizardSteps::lastImplemented()`) —
 * tant que les passes suivantes n'ont pas ajouté leur handler, le brouillon
 * reste utilisable sans étape « fantôme ».
 *
 * Le blueprint reconstruit est **cross-validé avant sauvegarde** par
 * `ModuleBlueprint::fromDraftJson()` (Pass A) : types de champs contre le
 * `FieldRegistry`, options contre `FieldType::optionsRules()`, cibles de
 * relation contre `StudioRelationTargetResolver`, clés d'entité en double.
 * C'est bien le constructeur *permissif* qui est utilisé — un brouillon en
 * cours de saisie n'a pas à satisfaire le schéma complet, seules les sections
 * déjà présentes sont vérifiées. Aucun handler d'étape n'a donc à redire cette
 * validation : elle vaut pour toutes, présentes et futures.
 */
final class SaveStudioWizardStep
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StudioWizardSteps $steps,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws InvalidModuleBlueprintException
     */
    public function __invoke(ModuleBlueprintDraft $draft, StudioStepHandler $handler, array $validated): ModuleBlueprintDraft
    {
        $draft->blueprint = $handler->fill($validated, $draft->blueprint);

        ModuleBlueprint::fromDraftJson((string) json_encode($draft->blueprint));

        if ($handler->number() === 1) {
            $draft->vendor_slug = $draft->blueprint['identity']['name'];
            $draft->title = $draft->blueprint['identity']['title'];
        }

        $draft->current_step = max($draft->current_step, min($handler->number() + 1, $this->steps->lastImplemented()));
        $draft->save();

        $this->audit->record('studio.draft.step_saved', $draft, ['step' => $handler->number()]);

        return $draft;
    }
}
