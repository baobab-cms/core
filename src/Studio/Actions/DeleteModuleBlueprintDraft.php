<?php

declare(strict_types=1);

namespace Baobab\Studio\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Studio\Exceptions\GeneratedDraftCannotBeDeletedException;
use Baobab\Studio\Models\ModuleBlueprintDraft;

final class DeleteModuleBlueprintDraft
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws GeneratedDraftCannotBeDeletedException
     */
    public function __invoke(ModuleBlueprintDraft $draft): void
    {
        if ($draft->isGenerated()) {
            throw GeneratedDraftCannotBeDeletedException::forDraft($draft);
        }

        $this->audit->record('studio.draft.deleted', $draft, ['vendor_slug' => $draft->vendor_slug]);

        $draft->delete();
    }
}
