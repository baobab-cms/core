<?php

declare(strict_types=1);

namespace Baobab\Branding\Support;

use Baobab\Branding\Models\Font;

/**
 * Génère les blocs `@font-face` pour les polices réellement référencées par
 * la cascade résolue (spec 18 §4.1 : « les familles référencées », jamais le
 * registre entier). Une police est « référencée » si son nom de famille
 * apparaît dans l'une des 3 valeurs résolues du groupe `fonts`
 * (`body`/`heading`/`mono`, qui portent une pile CSS complète — d'où le
 * containment plutôt qu'une égalité stricte).
 */
final class FontFaceGenerator
{
    /**
     * @param  array<string, string>  $resolvedFonts  Groupe `fonts` résolu (body/heading/mono).
     */
    public function generate(array $resolvedFonts): string
    {
        $blocks = [];
        $emitted = [];

        foreach (Font::query()->get() as $font) {
            if (isset($emitted[$font->id])) {
                continue;
            }

            $referenced = false;

            foreach ($resolvedFonts as $value) {
                if (str_contains($value, $font->family)) {
                    $referenced = true;

                    break;
                }
            }

            if (! $referenced) {
                continue;
            }

            $emitted[$font->id] = true;
            $blocks[] = $this->blocksFor($font);
        }

        return implode("\n", array_filter($blocks));
    }

    /**
     * URL du fichier le plus pertinent pour un preload (spec 18 §4.3 : un
     * seul preload, jamais une rafale) — la police `body` uniquement,
     * résolue par l'appelant. Préfère le fichier `variable` (couvre toutes
     * les graisses en un seul octet servi), sinon le poids le plus léger
     * disponible.
     */
    public function primaryFileUrl(string $resolvedValue): ?string
    {
        foreach (Font::query()->get() as $font) {
            if (! str_contains($resolvedValue, $font->family)) {
                continue;
            }

            $files = $font->files;

            if ($files === []) {
                return null;
            }

            $fileName = $files['variable'] ?? $files[array_key_first($files)];

            return '/baobab/fonts/'.$font->slug.'/'.$fileName;
        }

        return null;
    }

    private function blocksFor(Font $font): string
    {
        $rules = [];

        foreach ($font->files as $key => $fileName) {
            // Les clés numériques ("400", "700") sont recastées en int par
            // PHP à la décodification JSON — recastées ici en string.
            [$weight, $style] = $this->weightAndStyle((string) $key, $font->is_variable);
            $url = '/baobab/fonts/'.$font->slug.'/'.$fileName;

            $rules[] = <<<CSS
            @font-face {
              font-family: '{$font->family}';
              src: url('{$url}') format('woff2');
              font-weight: {$weight};
              font-style: {$style};
              font-display: swap;
            }
            CSS;
        }

        return implode("\n", $rules);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function weightAndStyle(string $key, bool $isVariable): array
    {
        if ($key === 'variable') {
            return [$isVariable ? '100 900' : '400', 'normal'];
        }

        if (str_ends_with($key, '-italic')) {
            return [substr($key, 0, -strlen('-italic')), 'italic'];
        }

        return [$key, 'normal'];
    }
}
