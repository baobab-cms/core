<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\Media;

/**
 * Déplace un ou plusieurs médias vers un dossier (spec 06 §2) — ne touche à
 * aucun fichier sur le disque, seulement `folder_id` : les URLs ne cassent
 * jamais.
 */
final class MoveMedia
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<Media>  $items
     */
    public function __invoke(array $items, ?int $folderId): void
    {
        foreach ($items as $media) {
            $media->update(['folder_id' => $folderId]);

            $this->audit->record('media.moved', $media, ['folder_id' => $folderId]);
        }
    }
}
