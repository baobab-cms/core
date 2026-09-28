<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Branding\Support\ContrastSafeColor;
use Baobab\Branding\Support\DesignTokenSchema;
use Baobab\Branding\Support\FontFaceGenerator;
use Baobab\Branding\Support\PublishFontAssets;
use Baobab\Branding\Support\ResolveDesignTokens;
use Baobab\Facades\Hook;
use Illuminate\Support\Facades\File;

/**
 * Compile la cascade de design tokens (spec 18 §4.1) en artefact CSS statique
 * — jamais un calcul au rendu (§3.2). Nom de fichier basé sur le hash du
 * contenu (`public/baobab/tokens-{hash}.css`) : cache-busting naturel,
 * écriture protégée (sans effet si le hash n'a pas changé, patron
 * `CompileGraphqlSchema`), ancien artefact supprimé. Rejouée à l'activation
 * d'un thème et à la sauvegarde du branding — voir
 * `BaobabServiceProvider::registerDesignTokenCompilationListener()`. Depuis
 * Pass B (spec 18 §5), l'artefact porte aussi les `@font-face` des familles
 * réellement référencées (`FontFaceGenerator`) — `PublishFontAssets` est
 * invoquée en tête de `buildCss()` pour s'auto-réparer avant émission.
 */
final class CompileDesignTokens
{
    private const string DIRECTORY = 'baobab';

    public function __construct(
        private readonly ResolveDesignTokens $resolve,
        private readonly PublishFontAssets $publishFonts,
        private readonly FontFaceGenerator $fontFaces,
    ) {}

    public function __invoke(): string
    {
        $css = $this->buildCss(($this->resolve)());
        $hash = hash('xxh128', $css);

        $directory = public_path(self::DIRECTORY);
        $path = "{$directory}/tokens-{$hash}.css";

        File::ensureDirectoryExists($directory);

        $previousHash = $this->currentHash($directory, $hash);

        if (! File::exists($path)) {
            File::put($path, $css);
        }

        foreach (glob("{$directory}/tokens-*.css") ?: [] as $stale) {
            if ($stale !== $path) {
                File::delete($stale);
            }
        }

        Hook::action('baobab.branding.compiled', $previousHash, $hash);

        return $path;
    }

    /**
     * @param  array<string, array<string, string>>  $tokens
     */
    public function buildCss(array $tokens): string
    {
        ($this->publishFonts)();

        $lines = [];

        foreach (DesignTokenSchema::GROUPS as $group => $keys) {
            foreach ($keys as $key) {
                $lines[] = '  '.DesignTokenSchema::cssVar($group, $key).': '.($tokens[$group][$key] ?? '').';';
            }
        }

        // Dérivé, pas un jeton du vocabulaire fermé (spec 18 §2.1-2.4) : un
        // site ne le règle jamais lui-même. Garantit le seuil AA (§13.4
        // règle 3) du remplissage plein du bouton primaire (§5.5) quelle que
        // soit la couleur de marque choisie, sans figer `primary` sur la
        // palette fixe du produit — voir suivi n° 368.
        $primary = $tokens['colors']['primary'] ?? DesignTokenSchema::CORE_DEFAULTS['colors']['primary'];
        $onPrimary = $tokens['colors']['on-primary'] ?? DesignTokenSchema::CORE_DEFAULTS['colors']['on-primary'];
        $lines[] = '  --bb-color-primary-strong: '.ContrastSafeColor::ensureContrast($primary, $onPrimary).';';

        $root = ":root {\n".implode("\n", $lines)."\n}\n";

        /** @var array<string, string> $fonts */
        $fonts = $tokens['fonts'] ?? [];
        $fontFaces = $this->fontFaces->generate($fonts);

        return $fontFaces === '' ? $root : "{$fontFaces}\n\n{$root}";
    }

    private function currentHash(string $directory, string $newHash): ?string
    {
        foreach (glob("{$directory}/tokens-*.css") ?: [] as $existing) {
            if (preg_match('/tokens-([a-f0-9]+)\.css$/', $existing, $matches) === 1 && $matches[1] !== $newHash) {
                return $matches[1];
            }
        }

        return null;
    }
}
