<?php

declare(strict_types=1);

namespace Baobab\Media\Support;

use GdImage;

/**
 * Normalisation EXIF pour les JPEG (spec 06 §3.2) : lit l'orientation, tourne
 * les pixels en conséquence via GD, puis ré-encode — ce qui élimine tout
 * l'EXIF restant (GPS inclus) puisque GD ne le préserve pas à la sauvegarde.
 * Simplification assumée (décision validée) : v1 = tout ou rien, pas de
 * rétention fine par tag EXIF. Sans Intervention Image (réservé au point 2
 * de M4) — uniquement des extensions déjà présentes dans PHP (exif, gd).
 */
final class ExifNormalizer
{
    public function normalize(string $path): void
    {
        if (exif_imagetype($path) !== IMAGETYPE_JPEG) {
            return;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        $image = @imagecreatefromjpeg($path);

        if ($image === false) {
            return;
        }

        $image = $this->applyOrientation($image, $orientation);

        imagejpeg($image, $path, 92);
        imagedestroy($image);
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        return match ($orientation) {
            2 => $this->flipped($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0) ?: $image,
            4 => $this->flipped($image, IMG_FLIP_VERTICAL),
            5 => imagerotate($this->flipped($image, IMG_FLIP_HORIZONTAL), 90, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            7 => imagerotate($this->flipped($image, IMG_FLIP_HORIZONTAL), -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function flipped(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
