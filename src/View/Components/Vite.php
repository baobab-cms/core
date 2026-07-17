<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Rendering\ActiveThemeResolver;
use Baobab\Themes\Actions\PublishThemeAssets;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\File;
use Illuminate\View\Component;

/**
 * `<x-baobab::vite entry="app" />` (spec 03 §8) — émet les balises
 * `<link>`/`<script type="module">` du thème actif à partir de son propre
 * manifest Vite (`public/themes/{slug}/build/.vite/manifest.json`, publié
 * par `PublishThemeAssets` à l'activation ; `.vite/` est l'emplacement par
 * défaut du manifest depuis Vite 5+, pas `build/manifest.json`).
 *
 * Écart assumé avec la lettre de la spec : la façade `Vite` de Laravel est
 * un service **global**, configuré pour un seul manifest (celui de
 * l'admin — `resources/views/layouts/admin.blade.php`). La détourner par
 * requête pour un manifest de thème introduirait un état global mutable
 * fragile. Ce composant lit directement le JSON du manifest et résout les
 * deux entrées conventionnelles `resources/assets/css/{entry}.css` et
 * `resources/assets/js/{entry}.js` — suffisant pour un thème (un seul point
 * d'entrée, pas de HMR à gérer en production).
 *
 * Slug résolu par `PublishThemeAssets::slugFor()` — seule source de vérité,
 * partagée pour rester synchronisée avec le chemin de publication.
 */
final class Vite extends Component
{
    /** @var list<string> */
    public array $css = [];

    /** @var list<string> */
    public array $js = [];

    public function __construct(ActiveThemeResolver $resolver, public string $entry = 'app')
    {
        $theme = $resolver->current();

        if ($theme === null) {
            return;
        }

        $slug = PublishThemeAssets::slugFor($theme);
        $manifestPath = public_path("themes/{$slug}/build/.vite/manifest.json");

        if (! File::exists($manifestPath)) {
            return;
        }

        /** @var array<string, array{file: string, css?: list<string>}> $manifest */
        $manifest = json_decode(File::get($manifestPath), associative: true) ?? [];

        $baseUrl = "/themes/{$slug}/build/";

        $cssEntry = $manifest["resources/assets/css/{$entry}.css"] ?? null;
        $jsEntry = $manifest["resources/assets/js/{$entry}.js"] ?? null;

        $cssFiles = [];

        if ($cssEntry !== null) {
            $cssFiles[] = $cssEntry['file'];
        }

        if ($jsEntry !== null) {
            $this->js[] = $baseUrl.$jsEntry['file'];
            $cssFiles = [...$cssFiles, ...($jsEntry['css'] ?? [])];
        }

        $this->css = array_values(array_map(fn (string $file): string => $baseUrl.$file, array_unique($cssFiles)));
    }

    public function render(): ViewContract
    {
        return view('baobab::components.vite');
    }
}
