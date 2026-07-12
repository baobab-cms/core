<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Illuminate\Support\Facades\Storage;

/**
 * Purge définitive d'un média déjà en corbeille (M4 point 3, spec 06 §5) —
 * supprime réellement l'original, l'édition et toutes les variantes du
 * disque, puis la ligne elle-même (hard delete). Irréversible : appelée
 * seulement depuis la corbeille (jamais depuis la suppression normale) ou
 * par la commande de purge programmée `media:purge-trash`.
 */
final class PurgeMedia
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        $disk->delete($media->path);

        if ($media->edited_path !== null) {
            $disk->delete($media->edited_path);
        }

        foreach ((array) $media->conversions as $conversion) {
            foreach ((array) ($conversion['formats'] ?? []) as $path) {
                $disk->delete($path);
            }
        }

        MediaUsage::where('media_id', $media->id)->delete();

        $this->audit->record('media.purged', null, ['file_name' => $media->file_name, 'uuid' => $media->uuid]);

        $media->forceDelete();
    }
}
