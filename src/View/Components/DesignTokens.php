<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Branding\Actions\CompileDesignTokens;
use Baobab\Branding\Support\FontFaceGenerator;
use Baobab\Branding\Support\ResolveDesignTokens;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\File;
use Illuminate\View\Component;

/**
 * `<x-baobab::design-tokens />` (spec 18 §4.3) — émet la feuille de style
 * compilée (`CompileDesignTokens`) dans le `<head>`. Si l'artefact est
 * introuvable (déploiement frais, disque purgé), compile à la volée et sert
 * le résultat en `<style>` inline pour cette requête — jamais une page sans
 * tokens (même philosophie que `RenderHomepage`, suivi n° 55) — pendant que
 * l'artefact est réécrit sur disque pour les requêtes suivantes.
 *
 * Depuis Pass B, émet aussi un unique `<link rel="preload">` pour le fichier
 * de la police `body` (§4.3 : « une seule, la plus critique »).
 */
final class DesignTokens extends Component
{
    public ?string $href = null;

    public ?string $inlineCss = null;

    public ?string $preloadHref = null;

    public function __construct(ResolveDesignTokens $resolve, CompileDesignTokens $compile, FontFaceGenerator $fontFaces)
    {
        $tokens = $resolve();
        $this->preloadHref = $fontFaces->primaryFileUrl($tokens['fonts']['body'] ?? '');

        $directory = public_path('baobab');
        $existing = File::isDirectory($directory) ? glob("{$directory}/tokens-*.css") : [];

        if ($existing !== [] && $existing !== false) {
            $this->href = '/baobab/'.basename($existing[0]);

            return;
        }

        $this->inlineCss = $compile->buildCss($tokens);
        $compile();
    }

    public function render(): ViewContract
    {
        return view('baobab::components.design-tokens');
    }
}
