<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\ContentTypes\Support\ContentTrash;
use Baobab\Facades\Hook;
use Illuminate\Database\Eloquent\Model;

/**
 * Sort un contenu de la corbeille (spec 09 §8, §10 décision 1). Un contenu
 * `published` au moment de sa mise à la corbeille revient en `draft` —
 * republier est un acte volontaire, aucune URL ne réapparaît par surprise.
 * Tout autre statut est restauré tel quel. Nommée distinctement de
 * RestoreArchivedContentEntry (archivage éditorial, spec 09 §2.2) et de
 * RestoreContentRevision (révision) malgré le même verbe métier « restore »
 * côté spec.
 */
final class RestoreContentEntryFromTrash
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ContentType $contentType, Model $entry): Model
    {
        $wasPublished = $entry->getAttribute('status') === 'published';

        ContentTrash::restore($entry);

        if ($wasPublished) {
            $entry->update(['status' => 'draft']);
        }

        $this->audit->record('content.restored_from_trash', $entry, ['content_type' => $contentType->key, 'was_published' => $wasPublished]);

        Hook::action('baobab.content.restored_from_trash', $contentType, $entry);

        return $entry;
    }
}
