<?php

declare(strict_types=1);

namespace Baobab\Admin\Themes\Http\Controllers;

use Baobab\Themes\Actions\SaveThemeBlueprint;
use Baobab\Themes\Exceptions\InvalidThemeBlueprintException;
use Baobab\Themes\Exceptions\ThemeBlueprintAlreadyExistsException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

/**
 * Ébauche Studio thèmes (spec 17 §1, M8 point 5 Pass B) : édite l'identité,
 * les menus, les zones de widgets et les supports d'un `theme.json` sur
 * disque (`themes/{slug}/theme.json`) — l'entrée même que consomme
 * `baobab:make:theme`, pas un modèle en base. `content_types` et la
 * génération complète restent l'affaire de la CLI (spec 17 §1), pas édités
 * ici. Accès gouverné par `baobab.system.themes.manage` (même périmètre que
 * l'écran Thèmes, routes/admin.php) — aucun blueprint Studio général
 * n'existe encore (M8 point 1 non construit), pas de table dédiée pour
 * cette ébauche.
 */
final class ThemeBlueprintController
{
    public function index(): View
    {
        $paths = glob(base_path('themes/*/theme.json'));

        $blueprints = collect($paths !== false ? $paths : [])
            ->map(fn (string $path): ?array => $this->decode($path))
            ->filter()
            ->sortBy('name')
            ->values();

        return view('baobab::admin.themes.studio.index', ['blueprints' => $blueprints]);
    }

    public function create(): View
    {
        return view('baobab::admin.themes.studio.create');
    }

    public function store(Request $request, SaveThemeBlueprint $action): RedirectResponse
    {
        $data = $request->validate($this->rules(requireSlug: true));

        try {
            $action($this->attributesFrom($data, (string) $data['slug']), isCreate: true);
        } catch (ThemeBlueprintAlreadyExistsException|InvalidThemeBlueprintException $e) {
            return back()->withInput()->withErrors(['blueprint' => $e->getMessage()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.themes.studio.created')]);

        return redirect()->route('admin.themes.studio.edit', ['slug' => $data['slug']]);
    }

    public function edit(string $slug): View
    {
        $path = base_path("themes/{$slug}/theme.json");

        abort_unless(File::isFile($path), 404);

        $blueprint = $this->decode($path) ?? [];

        return view('baobab::admin.themes.studio.edit', [
            'slug' => $slug,
            'blueprint' => $blueprint,
            'menusText' => $this->formatKeyValueList((array) ($blueprint['menus'] ?? [])),
            'widgetZonesText' => $this->formatKeyValueList((array) ($blueprint['widget_zones'] ?? [])),
        ]);
    }

    public function update(Request $request, string $slug, SaveThemeBlueprint $action): RedirectResponse
    {
        $data = $request->validate($this->rules(requireSlug: false));

        try {
            $action($this->attributesFrom($data, $slug));
        } catch (InvalidThemeBlueprintException $e) {
            return back()->withInput()->withErrors(['blueprint' => $e->getMessage()]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.themes.studio.updated')]);

        return redirect()->route('admin.themes.studio.edit', ['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $requireSlug): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:1'],
            'menus' => ['nullable', 'string'],
            'widget_zones' => ['nullable', 'string'],
            'supports' => ['array'],
            'supports.*' => ['string', Rule::in(['search'])],
        ];

        if ($requireSlug) {
            $rules['slug'] = ['required', 'string', 'regex:/^[a-z][a-z0-9-]*$/'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, slug: string, menus: array<string, string>, widget_zones: array<string, string>, supports: list<string>}
     */
    private function attributesFrom(array $data, string $slug): array
    {
        return [
            'name' => (string) $data['name'],
            'slug' => $slug,
            'menus' => $this->parseKeyValueList((string) ($data['menus'] ?? '')),
            'widget_zones' => $this->parseKeyValueList((string) ($data['widget_zones'] ?? '')),
            'supports' => $data['supports'] ?? [],
        ];
    }

    /**
     * Format « clé: Libellé », une paire par ligne — pas de widget de liste
     * dynamique (aucune librairie de tri/répétition dans ce code base,
     * cohérent avec le renoncement déjà acté pour les menus/widgets M6).
     *
     * @return array<string, string>
     */
    private function parseKeyValueList(string $raw): array
    {
        $pairs = [];

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $label] = explode(':', $line, 2);
            $pairs[trim($key)] = trim($label);
        }

        return $pairs;
    }

    /**
     * Inverse de `parseKeyValueList()` — préremplit le textarea d'édition à
     * partir du `menus`/`widget_zones` existant.
     *
     * @param  array<string, string>  $pairs
     */
    private function formatKeyValueList(array $pairs): string
    {
        return collect($pairs)
            ->map(fn (string $label, string $key): string => "{$key}: {$label}")
            ->implode("\n");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $path): ?array
    {
        $json = File::get($path);
        $data = json_decode($json, associative: true);

        return is_array($data) ? $data : null;
    }
}
