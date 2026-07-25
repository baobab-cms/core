<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Branding\Exceptions\FontInUseException;
use Baobab\Branding\Models\BrandingSetting;
use Baobab\Branding\Models\Font;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Branding\Support\ResolveDesignTokens;
use Baobab\Facades\Hook;
use Baobab\Rendering\ActiveThemeResolver;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Suppression protégée d'une police (spec 18 §5.5) : refuse si la famille
 * est référencée par le résultat compilé courant de la cascade (protection
 * native, patron `DeleteMedia` — jamais un scan différé, contre-exemple
 * suivi n° 66). Les polices `source=bundled` ne sont jamais supprimables :
 * référencées en dur par les défauts Core (`DesignTokenSchema::CORE_DEFAULTS`),
 * les retirer casserait l'identité par défaut du produit.
 */
final class DeleteFont
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ResolveDesignTokens $resolve,
        private readonly ActiveThemeResolver $themeResolver,
    ) {}

    public function __invoke(Font $font): void
    {
        if ($font->source === Font::SOURCE_BUNDLED) {
            throw new RuntimeException('Les polices fournies par le Core ne peuvent pas être supprimées.');
        }

        $this->assertNotInUse($font);

        Hook::action('baobab.fonts.deleting', $font);

        $this->audit->record('font.deleted', $font, ['family' => $font->family]);

        $directory = storage_path("app/baobab/fonts/{$font->slug}");

        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        $font->delete();

        Hook::action('baobab.fonts.deleted', $font);
    }

    private function assertNotInUse(Font $font): void
    {
        $resolved = ($this->resolve)()['fonts'] ?? [];

        foreach (DesignTokenSchema::GROUPS['fonts'] as $slot) {
            if (! str_contains((string) ($resolved[$slot] ?? ''), $font->family)) {
                continue;
            }

            throw FontInUseException::forFont($font, $slot, $this->levelFor($slot, $font->family));
        }
    }

    private function levelFor(string $slot, string $family): string
    {
        /** @var array<string, string> $adminFonts */
        $adminFonts = BrandingSetting::current()->tokens['fonts'] ?? [];

        if (str_contains($adminFonts[$slot] ?? '', $family)) {
            return 'admin';
        }

        $theme = $this->themeResolver->current();

        /** @var array<string, string> $themeFonts */
        $themeFonts = $theme?->manifest['tokens']['fonts'] ?? [];

        if (str_contains($themeFonts[$slot] ?? '', $family)) {
            return 'theme';
        }

        return 'core';
    }
}
