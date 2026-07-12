<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Media\Exceptions\MediaInUseException;
use Baobab\Media\Models\Media;

/**
 * Suppression protégée d'un média (M4 point 3, spec 06 §5). Soft delete
 * uniquement (`Media` utilise déjà `SoftDeletes`) — le fichier reste sur
 * disque, purgé plus tard par `media:purge-trash` après la rétention
 * configurée. Refuse de supprimer un média utilisé (`media_usages`) sauf
 * `force: true` — l'appelant (contrôleur) décide comment présenter ce refus
 * (409 JSON ou confirmation admin).
 */
final class DeleteMedia
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media, bool $force = false): void
    {
        if (! $force && $media->usages()->exists()) {
            throw MediaInUseException::forMedia($media);
        }

        Hook::action('baobab.media.deleting', $media);

        $this->audit->record('media.deleted', $media, ['file_name' => $media->file_name]);

        $media->delete();

        Hook::action('baobab.media.deleted', $media);
    }
}
