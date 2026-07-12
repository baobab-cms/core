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
}
