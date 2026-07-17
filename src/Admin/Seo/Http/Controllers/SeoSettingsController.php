<?php

declare(strict_types=1);

namespace Baobab\Admin\Seo\Http\Controllers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Seo\Actions\UpdateSeoSettings;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Baobab\Seo\Models\SeoSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran « Réglages > SEO » (spec 07 §2.2) : nom du site, séparateur, image
 * de partage par défaut, description par défaut, gabarit de titre par
 * Content Type adressable. Patron exact `ReadingSettingsController` — mini
 * écran dédié, pas le framework générique de réglages (spec-admin §5.1).
 * Accès gouverné par `baobab.system.seo.manage` au niveau de la route
 * (routes/admin.php).
 */
final class SeoSettingsController
{
    public function index(): View
    {
        $setting = SeoSetting::current();
        $contentTypes = ContentType::where('is_addressable', true)->orderBy('key')->get();

        $titleTemplates = $contentTypes
            ->mapWithKeys(fn (ContentType $type): array => [
                $type->key => SeoContentTypeSetting::forContentType($type)->title_template,
            ]);

        return view('baobab::admin.seo.index', [
            'setting' => $setting->load('defaultShareMedia'),
            'contentTypes' => $contentTypes,
            'titleTemplates' => $titleTemplates,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'title_separator' => ['required', 'string', 'max:10'],
            'default_share_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'default_meta_description' => ['nullable', 'string', 'max:1000'],
            'title_templates' => ['nullable', 'array'],
            'title_templates.*' => ['nullable', 'string', 'max:255'],
        ]);

        app(UpdateSeoSettings::class)(
            [
                'site_name' => $validated['site_name'] ?? null,
                'title_separator' => $validated['title_separator'],
                'default_share_media_id' => $validated['default_share_media_id'] ?? null,
                'default_meta_description' => $validated['default_meta_description'] ?? null,
            ],
            $validated['title_templates'] ?? [],
        );

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.seo.updated')]);

        return redirect()->route('admin.seo.index');
    }
}
