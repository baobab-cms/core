<?php

declare(strict_types=1);

namespace Baobab\Media\Conversions;

use Baobab\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Résout l'URL WebP d'un preset pour un média (spec 06 §4.1). Si le preset a
 * déjà une variante, la retourne directement ; sinon (preset déclaré après
 * coup par un module fraîchement activé) la génère **synchroniquement** à
 * cette première requête puis la persiste — pas de queue ici, l'appelant
 * attend une réponse immédiate.
 */
final class MediaVariantResolver
{
    public function __construct(private readonly PresetRegistry $presets) {}

    public function resolve(Media $media, string $preset): ?string
    {
        if (! $this->presets->has($preset)) {
            return null;
        }

        $conversions = (array) $media->conversions;

        if (! isset($conversions[$preset])) {
            $job = new GenerateMediaConversions($media, $preset);
            app()->call([$job, 'handle']);
            $media = $media->fresh() ?? $media;
        }

        $formats = $media->conversions[$preset]['formats'] ?? null;

        if ($formats === null) {
            return null;
        }

        $path = $formats['webp'] ?? reset($formats);

        return $path === false ? null : Storage::disk($media->disk)->url($path);
    }

    /**
     * Sources responsives pour `<x-baobab::img>` (spec 06 §6) : srcset par
     * format moderne (avif/webp) + srcset du format d'origine, construits
     * depuis les variantes déjà générées de même ratio que le preset demandé
     * (mélanger des recadrages différents dans un srcset fausserait le rendu).
     * Seule la variante du preset demandé est générée à la demande — jamais
     * les autres presets, ni toute la bibliothèque.
     *
     * @return array{src: string, width: int, height: int, source_sets: array<string, string>, fallback_srcset: string|null}|null
     */
    public function sources(Media $media, string $preset): ?array
    {
        if ($this->resolve($media, $preset) === null) {
            return null;
        }

        $media = $media->fresh() ?? $media;
        $conversions = (array) $media->conversions;
        $requested = $conversions[$preset] ?? null;

        if (! is_array($requested) || ! isset($requested['formats'], $requested['width'], $requested['height'])) {
            return null;
        }

        $requestedRatio = (int) $requested['height'] === 0 ? null : (int) $requested['width'] / (int) $requested['height'];
        $disk = Storage::disk($media->disk);

        /** @var array<string, array<int, string>> $byFormat format => [largeur => URL] */
        $byFormat = [];

        foreach ($conversions as $variant) {
            if (! is_array($variant) || ! isset($variant['formats'], $variant['width'], $variant['height'])) {
                continue;
            }

            $width = (int) $variant['width'];
            $height = (int) $variant['height'];

            if ($requestedRatio !== null && $height !== 0 && abs($width / $height - $requestedRatio) / $requestedRatio > 0.02) {
                continue;
            }

            foreach ((array) $variant['formats'] as $format => $path) {
                $byFormat[(string) $format][$width] ??= $disk->url((string) $path);
            }
        }

        $modernMimes = ['avif' => 'image/avif', 'webp' => 'image/webp'];
        $sourceSets = [];

        foreach ($modernMimes as $format => $mime) {
            if (isset($byFormat[$format])) {
                $sourceSets[$mime] = $this->toSrcset($byFormat[$format]);
            }
        }

        $fallbackFormat = null;

        foreach (array_keys($byFormat) as $format) {
            if (! isset($modernMimes[$format])) {
                $fallbackFormat = $format;
                break;
            }
        }

        /** @var array<string, string> $requestedFormats */
        $requestedFormats = (array) $requested['formats'];
        $srcPath = $requestedFormats[$fallbackFormat] ?? reset($requestedFormats);

        if ($srcPath === false) {
            return null;
        }

        return [
            'src' => $disk->url($srcPath),
            'width' => (int) $requested['width'],
            'height' => (int) $requested['height'],
            'source_sets' => $sourceSets,
            'fallback_srcset' => $fallbackFormat !== null ? $this->toSrcset($byFormat[$fallbackFormat]) : null,
        ];
    }

    /**
     * @param  array<int, string>  $urlsByWidth
     */
    private function toSrcset(array $urlsByWidth): string
    {
        ksort($urlsByWidth);

        return implode(', ', array_map(
            fn (int $width): string => "{$urlsByWidth[$width]} {$width}w",
            array_keys($urlsByWidth),
        ));
    }
}
