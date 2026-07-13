<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Bascule en `archived` tout contenu `published` dont `unpublish_at` est
 * atteint (spec 09 §4) — appelée chaque minute par `content:unpublish-due`.
 * Réutilise ArchiveContentEntry pour chaque ligne due.
 * ContentType::unpublishAtColumnExists() ignore déjà proprement les Content
 * Types dont la table n'a pas la colonne — soit désactivée au blueprint,
 * soit construits avant son introduction (suivi n° 37,
 * EvolutionMigrationGenerator ne rattrape pas les colonnes de convention).
 */
final class ArchiveDueContentEntries
{
    public function __construct(private readonly ArchiveContentEntry $archive) {}

    public function __invoke(): int
    {
        $archived = 0;

        foreach (ContentType::whereNotNull('module_id')->get() as $contentType) {
            if (! $contentType->unpublishAtColumnExists()) {
                continue;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();

            if (! class_exists($modelClass)) {
                continue;
            }

            $due = $modelClass::query()
                ->where('status', 'published')
                ->whereNotNull('unpublish_at')
                ->where('unpublish_at', '<=', now())
                ->get();

            foreach ($due as $entry) {
                ($this->archive)($contentType, $entry);
                $archived++;
            }
        }

        return $archived;
    }
}
