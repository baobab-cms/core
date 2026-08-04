<?php

declare(strict_types=1);

namespace Baobab\Studio\Exceptions;

use Baobab\Studio\Models\ModuleBlueprintDraft;
use RuntimeException;

/**
 * Levée par DeleteModuleBlueprintDraft : un brouillon déjà généré
 * (`generated_at` renseigné) ne se supprime pas depuis l'écran Studio — le
 * module généré vit désormais sur disque et en base (`modules`), hors de la
 * portée de cette action.
 */
final class GeneratedDraftCannotBeDeletedException extends RuntimeException
{
    public static function forDraft(ModuleBlueprintDraft $draft): self
    {
        return new self(
            "Le brouillon « {$draft->vendor_slug} » a déjà été généré et ne peut plus être supprimé depuis cet écran."
        );
    }
}
