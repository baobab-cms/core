<?php

declare(strict_types=1);

namespace Baobab\Branding\Actions;

use Baobab\Branding\Support\DesignTokenSchema;
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
 * `BaobabServiceProvider::registerDesignTokenCompilationListener()`.
 */
final class CompileDesignTokens
{
    private const string DIRECTORY = 'baobab';

    public function __construct(private readonly ResolveDesignTokens $resolve) {}

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
        $lines = [];

        foreach (DesignTokenSchema::GROUPS as $group => $keys) {
            foreach ($keys as $key) {
                $lines[] = '  '.DesignTokenSchema::cssVar($group, $key).': '.($tokens[$group][$key] ?? '').';';
            }
        }

        return ":root {\n".implode("\n", $lines)."\n}\n";
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
