<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Wizard\StudioStepHandler;
use Baobab\Studio\Wizard\StudioWizardSteps;

/**
 * Sauvegarde la soumission d'une étape du wizard dans le blueprint JSON du
 * brouillon (spec-modules §5.4). `current_step` n'avance jamais au-delà de
 * la dernière étape implémentée (`StudioWizardSteps::lastImplemented()`) —
 * tant que B2/B3/B4 n'ont pas ajouté leur handler, rejouer l'étape 1 reste le
 * seul chemin possible, le brouillon reste utilisable sans étape « fantôme ».
 */
final class SaveStudioWizardStep
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StudioWizardSteps $steps,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(ModuleBlueprintDraft $draft, StudioStepHandler $handler, array $validated): ModuleBlueprintDraft
    {
        $draft->blueprint = $handler->fill($validated, $draft->blueprint);

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
