<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\Media;

/**
 * Restaure un média soft-deleted (M4 point 3, spec 06 §5) — annule la mise à
 * la corbeille, le fichier n'ayant de toute façon jamais été touché avant la
 * purge programmée.
 */
final class RestoreMediaFromTrash
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media): Media
    {
        $media->restore();

        $this->audit->record('media.restored_from_trash', $media);

        return $media;
    }
}
