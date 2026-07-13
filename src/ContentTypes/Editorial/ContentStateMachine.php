<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Editorial;

use Baobab\ContentTypes\Exceptions\InvalidContentTransitionException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;

/**
 * Graphe fermé des transitions du cycle éditorial (spec 09 §2.2) : toute
 * transition non listée est refusée, pas seulement masquée dans l'UI. Le
 * workflow de validation (submit/pending/approve/reject) n'existe que pour
 * les types qui l'ont activé (`"workflow": true` au blueprint, spec 09 §5) —
 * sur les autres, `pending` est un état inatteignable. Extensible par un
 * module via le filtre `baobab.content.transitions` (ex. un module de
 * traduction ajoutant `in_translation`), jamais par modification du Core.
 */
final class ContentStateMachine
{
    public function assertAllowed(ContentType $contentType, string $from, string $transition): void
    {
        if (! in_array($transition, $this->availableTransitions($contentType, $from), true)) {
            throw InvalidContentTransitionException::forTransition($transition, $from);
        }
    }

    /**
     * @return list<string>
     */
    public function availableTransitions(ContentType $contentType, string $from): array
    {
        return $this->graph($contentType)[$from] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function graph(ContentType $contentType): array
    {
        $graph = [
            'draft' => ['submit', 'publish', 'schedule', 'archive'],
            'pending' => ['approve', 'reject', 'publish', 'schedule', 'archive'],
            'published' => ['unpublish', 'archive'],
            'scheduled' => ['unpublish', 'archive', 'schedule', 'publish'],
            'archived' => ['restore'],
        ];

        if (! $contentType->workflowEnabled()) {
            $graph['draft'] = array_values(array_diff($graph['draft'], ['submit']));
            unset($graph['pending']);
        }

        /** @var array<string, list<string>> $filtered */
        $filtered = Hook::filter('baobab.content.transitions', $graph, $contentType);

        return $filtered;
    }
}
