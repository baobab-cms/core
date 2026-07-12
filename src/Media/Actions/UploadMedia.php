<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Media\Exceptions\DuplicateMediaDetectedException;
use Baobab\Media\Exceptions\InvalidMediaUploadException;
use Baobab\Media\Exceptions\MediaTooLargeException;
use Baobab\Media\Models\Media;
use Baobab\Media\Support\ExifNormalizer;
use Baobab\Media\Support\SvgSanitizer;
use Baobab\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload d'un fichier vers la bibliothèque de médias (spec 06 §1-3, M4 points
 * 1a/1b). Valide le MIME par contenu (jamais la seule extension) et la
 * taille, désinfecte les SVG, normalise l'orientation EXIF des JPEG et
 * supprime le reste de leurs métadonnées, puis stocke via l'abstraction
 * Storage — jamais de chemin absolu manipulé à la main (spec 06 §1.1).
 * L'autorisation (permission d'upload, SVG réservé à un rôle) est vérifiée
 * par l'appelant (policy) ; cette Action ne valide que le contenu du fichier
 * lui-même.
 */
final class UploadMedia
{
    public function __construct(
        private readonly SvgSanitizer $svgSanitizer,
        private readonly ExifNormalizer $exifNormalizer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  'reuse'|'new'|null  $duplicateAction  Décision explicite de l'appelant
     *                                               face à un doublon détecté (spec 06 §2) ; null = appliquer
     *                                               `baobab.media.duplicate_behavior`.
     */
    public function __invoke(UploadedFile $file, User $actor, array $metadata = [], ?string $duplicateAction = null): Media
    {
        $mimeType = (string) $file->getMimeType();

        $this->assertMimeTypeAllowed($mimeType);
        $this->assertSizeAllowed((int) $file->getSize(), $actor);

        $uuid = (string) Str::uuid();
        $extension = $this->extensionFor($file, $mimeType);
        $disk = (string) config('baobab.media.disk', 'public');
        $directory = 'media/'.now()->format('Y/m');
        $path = "{$directory}/{$uuid}.{$extension}";

        $contents = $mimeType === 'image/svg+xml'
            ? $this->svgSanitizer->sanitize((string) file_get_contents($file->getRealPath()))
            : (string) file_get_contents($file->getRealPath());

        Storage::disk($disk)->put($path, $contents);

        $absolutePath = Storage::disk($disk)->path($path);

        if ($mimeType === 'image/jpeg') {
            $this->exifNormalizer->normalize($absolutePath);
        }

        [$width, $height] = $this->dimensions($absolutePath, $mimeType);
        $checksum = hash('sha256', (string) file_get_contents($absolutePath));

        $existing = $duplicateAction === 'new' ? null : Media::where('checksum', $checksum)->first();

        if ($existing !== null) {
            $decision = $duplicateAction ?? (string) config('baobab.media.duplicate_behavior', 'ask');

            if ($decision === 'reuse') {
                Storage::disk($disk)->delete($path);

                return $existing;
            }

            if ($decision === 'ask') {
                Storage::disk($disk)->delete($path);

                throw DuplicateMediaDetectedException::forExisting($existing);
            }

            // 'allow' : le doublon est accepté sciemment, on continue la création.
        }

        $media = Media::create([
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => (int) Storage::disk($disk)->size($path),
            'width' => $width,
            'height' => $height,
            'title' => $metadata['title'] ?? null,
            'alt' => $metadata['alt'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'description' => $metadata['description'] ?? null,
            'checksum' => $checksum,
            'folder_id' => $metadata['folder_id'] ?? null,
            'author_id' => $actor->getKey(),
            'conversions' => [],
            'meta' => [],
        ]);

        $this->audit->record('media.uploaded', $media, ['mime_type' => $mimeType, 'size' => $media->size]);

        Hook::action('baobab.media.uploaded', $media);

        return $media;
    }

    private function assertMimeTypeAllowed(string $mimeType): void
    {
        /** @var list<string> $allowed */
        $allowed = (array) config('baobab.media.allowed_mime_types', []);

        if (! in_array($mimeType, $allowed, true)) {
            throw InvalidMediaUploadException::disallowedMimeType($mimeType);
        }
    }

    private function assertSizeAllowed(int $sizeInBytes, User $actor): void
    {
        $defaultMax = (int) config('baobab.media.max_upload_size', 67_108_864);

        /** @var int $max */
        $max = Hook::filter('baobab.media.uploading.max_size', $defaultMax, $actor);

        if ($sizeInBytes > $max) {
            throw MediaTooLargeException::forSize($sizeInBytes, $max);
        }
    }

    private function extensionFor(UploadedFile $file, string $mimeType): string
    {
        if ($mimeType === 'image/svg+xml') {
            return 'svg';
        }

        return $file->extension() ?: $file->getClientOriginalExtension();
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(string $absolutePath, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return [null, null];
        }

        $size = @getimagesize($absolutePath);

        if ($size === false) {
            return [null, null];
        }

        return [$size[0], $size[1]];
    }
}
