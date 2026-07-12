<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Annule l'édition en place d'un média (M4 point 2b, spec 06 §4.2) — supprime
 * le fichier `edited_path` et vide les colonnes associées, puis régénère les
 * presets depuis l'original vrai (`path`, jamais modifié).
 */
final class RestoreOriginalMedia
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media): Media
    {
        $disk = Storage::disk($media->disk);

        if ($media->edited_path !== null && $disk->exists($media->edited_path)) {
            $disk->delete($media->edited_path);
        }

        $media->update([
            'edited_path' => null,
            'edited_width' => null,
            'edited_height' => null,
        ]);

        $this->audit->record('media.restored', $media);

        GenerateMediaConversions::dispatch($media, force: true);

        return $media;
    }
}
