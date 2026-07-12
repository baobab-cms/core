<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Conversions\GenerateMediaConversions;
use Baobab\Media\Models\Media;

/**
 * Point focal d'un média (M4 point 2b, spec 06 §4.2) — pilote le recadrage
 * des presets `fit: crop` (GenerateMediaConversions::cropToFocalPoint).
 * Change le point focal sans régénérer immédiatement les presets `crop`
 * existants les laisserait affichés avec l'ancien cadrage, d'où la
 * régénération forcée de toutes les variantes à chaque changement.
 */
final class UpdateMediaFocalPoint
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media, float $focalX, float $focalY): Media
    {
        $media->update([
            'focal_x' => max(0.0, min(1.0, $focalX)),
            'focal_y' => max(0.0, min(1.0, $focalY)),
        ]);

        $this->audit->record('media.focal_point_updated', $media, ['focal_x' => $media->focal_x, 'focal_y' => $media->focal_y]);

        GenerateMediaConversions::dispatch($media, force: true);

        return $media;
    }
}
