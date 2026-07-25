<?php

declare(strict_types=1);

namespace Baobab\Branding\Exceptions;

use Baobab\Branding\Models\Font;
use RuntimeException;

/**
 * Levée par `DeleteFont` (spec 18 §5.5, suppression protégée) quand la
 * famille est référencée par le résultat compilé courant de la cascade
 * (`ResolveDesignTokens`). Porte le token et le niveau concernés pour que
 * l'appelant affiche un message explicite — même rationale que
 * `MediaInUseException` (leçon du suivi n° 66).
 */
final class FontInUseException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $slot,
        public readonly string $level,
    ) {
        parent::__construct($message);
    }

    public static function forFont(Font $font, string $slot, string $level): self
    {
        return new self(
            "La police « {$font->family} » est utilisée par le token « fonts.{$slot} » au niveau « {$level} » de la cascade et ne peut pas être supprimée.",
            $slot,
            $level,
        );
    }
}
