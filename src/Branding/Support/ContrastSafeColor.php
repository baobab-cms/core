<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

/**
 * Assombrit ou éclaircit une couleur de remplissage jusqu'à atteindre un
 * contraste WCAG 2.1 suffisant contre une couleur de premier plan donnée
 * (spec 18 §13.4 règle 3 : 4,5:1 pour du texte courant). Sert le correctif du
 * bouton primaire (§5.5 : `#C2571B` sur blanc donne 4,49:1, sous le seuil) —
 * calculé une seule fois à la compilation des tokens (`CompileDesignTokens`),
 * jamais au rendu (spec 18 §3.2).
 *
 * La direction (vers le noir ou vers le blanc) suit la luminance du premier
 * plan, pas une hypothèse sur le remplissage : un site qui personnalise
 * `primary` vers une teinte déjà claire, avec un `on-primary` sombre, doit
 * s'assombrir vers le blanc, pas vers le noir. Mélange linéaire par paliers
 * de 5 % jusqu'à 100 % (noir ou blanc plein), qui garantit le seuil dans la
 * quasi-totalité des cas — la seule zone théoriquement non couverte est une
 * bande de luminance du premier plan étroite (~0,175 à ~0,183) où ni le noir
 * ni le blanc plein n'atteignent 4,5:1 contre elle ; négligeable en pratique
 * pour une couleur de marque choisie par un humain.
 */
final class ContrastSafeColor
{
    private const float TARGET_RATIO = 4.5;

    private const int STEPS = 20;

    public static function ensureContrast(string $fillHex, string $foregroundHex, float $targetRatio = self::TARGET_RATIO): string
    {
        $fill = self::toRgb($fillHex);
        $foregroundLuminance = self::relativeLuminance(self::toRgb($foregroundHex));

        if (self::contrastRatio(self::relativeLuminance($fill), $foregroundLuminance) >= $targetRatio) {
            return self::toHex($fill);
        }

        $toward = $foregroundLuminance >= 0.5 ? [0, 0, 0] : [255, 255, 255];

        for ($step = 1; $step <= self::STEPS; $step++) {
            $mixed = self::mix($fill, $toward, $step / self::STEPS);

            if (self::contrastRatio(self::relativeLuminance($mixed), $foregroundLuminance) >= $targetRatio) {
                return self::toHex($mixed);
            }
        }

        return self::toHex($toward);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $from
     * @param  array{0: int, 1: int, 2: int}  $to
     * @return array{0: int, 1: int, 2: int}
     */
    private static function mix(array $from, array $to, float $ratio): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
            (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
            (int) round($from[2] + ($to[2] - $from[2]) * $ratio),
        ];
    }

    /**
     * Luminance relative WCAG 2.1 (§1.4.3), sRGB linéarisé.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function relativeLuminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(
            static fn (int $channel): float => self::linearize($channel / 255),
            $rgb,
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.03928
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function contrastRatio(float $luminanceA, float $luminanceB): float
    {
        $lighter = max($luminanceA, $luminanceB);
        $darker = min($luminanceA, $luminanceB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
