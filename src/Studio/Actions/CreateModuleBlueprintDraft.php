<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Studio\Models\ModuleBlueprintDraft;
use Baobab\Studio\Wizard\IdentityStepHandler;

/**
 * Crée un brouillon de blueprint (spec-modules §5.4) à partir des données
 * validées de l'étape 1 (Identité) — première écriture du parcours Wizard
 * Studio, `current_step` démarre à 1 (l'utilisateur reste sur cette même
 * étape pour la relire/corriger avant de continuer).
 */
final class CreateModuleBlueprintDraft
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $validatedIdentity  Données validées par IdentityStepHandler::rules().
     */
    public function __invoke(array $validatedIdentity): ModuleBlueprintDraft
    {
        $handler = new IdentityStepHandler;

        $blueprint = $handler->fill($validatedIdentity, ['blueprint_version' => 1]);

        $draft = ModuleBlueprintDraft::create([
            'vendor_slug' => $validatedIdentity['name'],
            'title' => $validatedIdentity['title'],
            'blueprint_version' => 1,
            'current_step' => 1,
            'blueprint' => $blueprint,
        ]);

        $this->audit->record('studio.draft.created', $draft, ['vendor_slug' => $draft->vendor_slug]);

        return $draft;
    }
}
