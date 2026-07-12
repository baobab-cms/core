<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Illuminate\Database\Eloquent\Model;

/**
 * Supprime (soft delete, colonne de convention posée par le générateur) une
 * ligne de contenu d'un Content Type construit. La corbeille et la
 * restauration elles-mêmes sont hors périmètre de M3 point 5 (voir M5).
 */
final class DeleteContentEntry
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ContentType $contentType, Model $entry): void
    {
        $entry->delete();

        $this->audit->record('content.deleted', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.deleted', $contentType, $entry);
    }
}
