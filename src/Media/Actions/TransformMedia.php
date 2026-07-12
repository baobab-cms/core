<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Exceptions\InvalidMediaUploadException;
use Baobab\Media\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Édition d'un média — recadrage/rotation/retournement (M4 point 2b, spec 06
 * §4.2). La composition de la transformation (rotation, retournement,
 * recadrage) est faite côté client par Cropper.js, qui exporte le résultat
 * en pixels ; cette Action ne fait que recevoir ce résultat, vérifier son
 * MIME réel et le stocker comme `edited_path` — jamais l'original (`path`),
 * qui reste intact. Repart toujours de l'original vrai (jamais d'une édition
 * précédente) : une édition remplace la précédente plutôt que de s'empiler
 * dessus, ce qui évite la dégradation cumulative d'un JPEG ré-encodé
 * plusieurs fois.
 */
final class TransformMedia
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media, UploadedFile $file): Media
    {
        $mimeType = (string) $file->getMimeType();

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw InvalidMediaUploadException::disallowedMimeType($mimeType);
        }

        $disk = Storage::disk($media->disk);

        if ($media->edited_path !== null && $disk->exists($media->edited_path)) {
            $disk->delete($media->edited_path);
        }

        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $directory = 'media/'.now()->format('Y/m');
        $path = "{$directory}/{$media->uuid}-edited.{$extension}";

        $disk->put($path, (string) file_get_contents($file->getRealPath()));

        $size = @getimagesize($disk->path($path));

        $media->update([
            'edited_path' => $path,
            'edited_width' => $size !== false ? $size[0] : null,
            'edited_height' => $size !== false ? $size[1] : null,
        ]);

        $this->audit->record('media.transformed', $media, ['edited_path' => $path]);

        GenerateMediaConversions::dispatch($media, force: true);

        return $media;
    }
}
