<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Media\Exceptions\DuplicateMediaDetectedException;
use Baobab\Media\Exceptions\ExternalMediaException;
use Baobab\Media\Models\Media;
use Baobab\Media\Support\OEmbedResolver;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ajoute un média « externe » à la bibliothèque (spec 06 §7.1, M4 point 4) :
 * une URL tierce (YouTube, Vimeo, Dailymotion) résolue par oEmbed. La vignette
 * du fournisseur est téléchargée et stockée comme fichier du média (la grille,
 * le picker et les champs médias fonctionnent sans cas particulier) ; le HTML
 * du lecteur embarqué vit dans meta.oembed. La détection de doublon porte sur
 * l'URL (même mécanique tri-état que UploadMedia). L'autorisation est
 * vérifiée par l'appelant (policy `create`).
 */
final class CreateExternalMedia
{
    public function __construct(
        private readonly OEmbedResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  'reuse'|'new'|null  $duplicateAction  Décision explicite de l'appelant
     *                                               face à un doublon détecté (spec 06 §2) ; null = appliquer
     *                                               `baobab.media.duplicate_behavior`.
     */
    public function __invoke(string $url, User $actor, array $metadata = [], ?string $duplicateAction = null): Media
    {
        $payload = $this->resolver->resolve($url);

        // Doublon = même URL externe déjà référencée — le checksum d'un média externe est
        // dérivé de l'URL, pas des octets de la vignette (qui entreraient en collision avec
        // un upload local de la même image).
        $checksum = hash('sha256', 'external:'.$url);

        $existing = $duplicateAction === 'new' ? null : Media::where('checksum', $checksum)->first();

        if ($existing !== null) {
            $decision = $duplicateAction ?? (string) config('baobab.media.duplicate_behavior', 'ask');

            if ($decision === 'reuse') {
                return $existing;
            }

            if ($decision === 'ask') {
                throw DuplicateMediaDetectedException::forExisting($existing);
            }

            // 'allow' : le doublon est accepté sciemment, on continue la création.
        }

        $uuid = (string) Str::uuid();
        $disk = (string) config('baobab.media.disk', 'public');

        [$path, $size, $width, $height] = $this->storeThumbnail($url, $payload['thumbnail_url'], $uuid, $disk);

        $media = Media::create([
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => $path,
            'file_name' => $payload['title'] ?? (string) parse_url($url, PHP_URL_HOST),
            'mime_type' => $payload['type'] === 'video' ? 'video/external' : 'application/external',
            'source' => 'external',
            'external_url' => $url,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'title' => $metadata['title'] ?? $payload['title'],
            'alt' => $metadata['alt'] ?? $payload['title'],
            'caption' => $metadata['caption'] ?? null,
            'description' => $metadata['description'] ?? null,
            'checksum' => $checksum,
            'folder_id' => $metadata['folder_id'] ?? null,
            'author_id' => $actor->getKey(),
            'conversions' => [],
            'meta' => ['oembed' => $payload],
        ]);

        $this->audit->record('media.external_created', $media, ['provider' => $payload['provider'], 'url' => $url]);

        Hook::action('baobab.media.uploaded', $media);

        return $media;
    }

    /**
     * Télécharge la vignette du fournisseur et la stocke comme fichier du
     * média — même convention de chemin que UploadMedia.
     *
     * @return array{0: string, 1: int, 2: int|null, 3: int|null}
     */
    private function storeThumbnail(string $url, ?string $thumbnailUrl, string $uuid, string $disk): array
    {
        if ($thumbnailUrl === null) {
            throw ExternalMediaException::thumbnailUnavailable($url);
        }

        try {
            $response = Http::timeout(10)->get($thumbnailUrl);
        } catch (Throwable) {
            throw ExternalMediaException::thumbnailUnavailable($url);
        }

        if (! $response->successful()) {
            throw ExternalMediaException::thumbnailUnavailable($url);
        }

        $contents = $response->body();
        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false) {
            throw ExternalMediaException::thumbnailUnavailable($url);
        }

        $extension = match ($dimensions['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $path = 'media/'.now()->format('Y/m')."/{$uuid}.{$extension}";
        Storage::disk($disk)->put($path, $contents);

        return [$path, strlen($contents), $dimensions[0], $dimensions[1]];
    }
}
