<?php

declare(strict_types=1);

namespace Baobab\Admin\Rendering\Http\Controllers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Rendering\Actions\UpdateReadingSettings;
use Baobab\Rendering\Models\ReadingSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran « Réglages > Lecture » (spec 03 §4, amendement du 17 juillet 2026) :
 * mode de la page d'accueil (page statique ou derniers contenus). Patron
 * exact `BrandingController` — mini écran dédié, pas le framework générique
 * de réglages (spec-admin §5.1). Accès gouverné par
 * `baobab.system.reading.manage` au niveau de la route (routes/admin.php).
 */
final class ReadingSettingsController
{
    public function index(): View
    {
        $setting = ReadingSetting::current();
        $contentTypes = ContentType::where('is_addressable', true)->orderBy('key')->get();

        $pageEntries = [];

        if ($setting->page_content_type_key !== null) {
            $contentType = $contentTypes->firstWhere('key', $setting->page_content_type_key);

            if ($contentType instanceof ContentType) {
                $pageEntries = $this->publishedEntries($contentType);
            }
        }

        return view('baobab::admin.reading.index', [
            'setting' => $setting,
            'contentTypes' => $contentTypes,
            'pageEntries' => $pageEntries,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Le <select> du mode porte une option vide (« par défaut, page
        // index du thème ») — converti en null avant validation, patron
        // "nullable" plutôt qu'un enum à trois valeurs incluant la chaîne
        // vide.
        $request->merge(['mode' => $request->input('mode') ?: null]);

        $addressableKeys = ContentType::where('is_addressable', true)->pluck('key')->all();

        $validated = $request->validate([
            'mode' => ['nullable', 'in:static_page,latest_posts'],
            'page_content_type_key' => ['required_if:mode,static_page', 'nullable', 'in:'.implode(',', $addressableKeys)],
            'page_entry_id' => [
                'required_if:mode,static_page',
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('mode') !== 'static_page' || $value === null) {
                        return;
                    }

                    $contentType = ContentType::where('key', $request->input('page_content_type_key'))->first();

                    if (! $contentType instanceof ContentType) {
                        return;
                    }

                    /** @var class-string<Model> $modelClass */
                    $modelClass = $contentType->modelClass();

                    if (! $modelClass::query()->where('status', 'published')->whereKey($value)->exists()) {
                        $fail('L\'entrée choisie est introuvable ou non publiée.');
                    }
                },
            ],
            'posts_content_type_key' => ['required_if:mode,latest_posts', 'nullable', 'in:'.implode(',', $addressableKeys)],
        ]);

        if ($validated['mode'] === null) {
            $validated['page_content_type_key'] = null;
            $validated['page_entry_id'] = null;
            $validated['posts_content_type_key'] = null;
        }

        app(UpdateReadingSettings::class)($validated);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.reading.updated')]);

        return redirect()->route('admin.reading.index');
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function publishedEntries(ContentType $contentType): array
    {
        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        return array_values($modelClass::query()
            ->where('status', 'published')
            ->get()
            ->map(fn (Model $entry): array => [
                'id' => (int) $entry->getKey(),
                'label' => $titleField !== null ? (string) $entry->getAttribute($titleField) : (string) $entry->getKey(),
            ])
            ->all());
    }
}
