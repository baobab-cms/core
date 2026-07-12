<?php

declare(strict_types=1);

namespace Baobab\Media\Conversions;

use Baobab\Media\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Génère les variantes manquantes d'un média (spec 06 §4.1) : pour chaque
 * preset enregistré, un fichier ré-encodé au format d'origine + un WebP
 * (+ AVIF si activé). L'original n'est jamais modifié, seulement lu. Ignore
 * silencieusement tout ce qui n'est pas une image raster (SVG compris — pas
 * de sens à le redimensionner en pixels).
 */
final class GenerateMediaConversions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly Media $media,
        private readonly ?string $onlyPreset = null,
        private readonly bool $force = false,
    ) {}

    public function handle(ImageManager $manager, PresetRegistry $presets): void
    {
        if (! $this->isRasterImage($this->media->mime_type)) {
            return;
        }

        // Une photo réelle décodée par GD tient facilement 50-100 Mo en mémoire (bitmap non
        // compressé) ; le memory_limit par défaut (souvent 128 Mo, hérité du php.ini web) est
        // taillé pour une requête HTTP classique, pas pour du traitement d'image. Relevé
        // uniquement pour ce job, pas globalement.
        ini_set('memory_limit', (string) config('baobab.media.job_memory_limit', '512M'));

        $sourcePath = Storage::disk($this->media->disk)->path($this->media->path);
        $conversions = (array) $this->media->conversions;

        foreach ($presets->all() as $name => $definition) {
            if ($this->onlyPreset !== null && $name !== $this->onlyPreset) {
                continue;
            }

            if (! $this->force && isset($conversions[$name])) {
                continue;
            }

            // Redécodé depuis le disque à chaque preset plutôt que cloné en mémoire : un seul
            // bitmap pleine résolution vivant à la fois, pas l'original + un clone simultanément.
            $image = $manager->read($sourcePath);
            $conversions[$name] = $this->generateVariant($image, $name, $definition);
            unset($image);
        }

        $this->media->update(['conversions' => $conversions]);
    }

    /**
     * @param  array{width?: int, height?: int, fit: string, quality?: int}  $definition
     * @return array{formats: array<string, string>, width: int, height: int}
     */
    private function generateVariant(ImageInterface $image, string $name, array $definition): array
    {
        $image = $this->applyFit($image, $definition);
        $quality = $definition['quality'] ?? 80;

        $directory = 'media/'.now()->format('Y/m');
        $extension = pathinfo($this->media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
        $basePath = "{$directory}/{$this->media->uuid}-{$name}";

        $disk = Storage::disk($this->media->disk);
        $formats = [];

        $original = $image->encodeByExtension($extension, quality: $quality);
        $originalPath = "{$basePath}.{$extension}";
        $disk->put($originalPath, (string) $original);
        $formats[$extension] = $originalPath;

        $webp = $image->toWebp(quality: $quality);
        $webpPath = "{$basePath}.webp";
        $disk->put($webpPath, (string) $webp);
        $formats['webp'] = $webpPath;

        if ((bool) config('baobab.media.avif_enabled', false)) {
            try {
                $avif = $image->toAvif(quality: $quality);
                $avifPath = "{$basePath}.avif";
                $disk->put($avifPath, (string) $avif);
                $formats['avif'] = $avifPath;
            } catch (Throwable) {
                // Support AVIF best-effort : selon le build GD/Imagick disponible, l'encodage
                // peut ne pas être supporté — on garde jpg/webp plutôt que de faire échouer tout le job.
            }
        }

        return ['formats' => $formats, 'width' => $image->width(), 'height' => $image->height()];
    }

    /**
     * @param  array{width?: int, height?: int, fit: string}  $definition
     */
    private function applyFit(ImageInterface $image, array $definition): ImageInterface
    {
        $width = $definition['width'] ?? null;
        $height = $definition['height'] ?? null;

        return match ($definition['fit']) {
            'fill' => $image->resize($width, $height ?? $width),
            'crop' => $this->cropToFocalPoint($image, $width ?? $image->width(), $height ?? $image->height()),
            default => $image->scaleDown($width, $height),
        };
    }

    private function cropToFocalPoint(ImageInterface $image, int $targetWidth, int $targetHeight): ImageInterface
    {
        $sourceWidth = $image->width();
        $sourceHeight = $image->height();

        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $scaledWidth = (int) round($sourceWidth * $scale);
        $scaledHeight = (int) round($sourceHeight * $scale);

        $image->resize($scaledWidth, $scaledHeight);

        $focalX = $this->media->focal_x ?? 0.5;
        $focalY = $this->media->focal_y ?? 0.5;

        $offsetX = (int) max(0, min($scaledWidth - $targetWidth, round($focalX * $scaledWidth - $targetWidth / 2)));
        $offsetY = (int) max(0, min($scaledHeight - $targetHeight, round($focalY * $scaledHeight - $targetHeight / 2)));

        return $image->crop($targetWidth, $targetHeight, $offsetX, $offsetY);
    }

    private function isRasterImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml';
    }
}
