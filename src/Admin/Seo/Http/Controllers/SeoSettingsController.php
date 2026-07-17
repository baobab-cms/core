<?php

declare(strict_types=1);

namespace Baobab\Admin\Seo\Http\Controllers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Seo\Actions\ComposeRobotsTxt;
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

        $typeSettings = $contentTypes
            ->mapWithKeys(fn (ContentType $type): array => [$type->key => SeoContentTypeSetting::forContentType($type)]);

        return view('baobab::admin.seo.index', [
            'setting' => $setting->load('defaultShareMedia'),
            'contentTypes' => $contentTypes,
            'typeSettings' => $typeSettings,
        ]);
    }

    public function update(Request $request, ComposeRobotsTxt $robotsTxt): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'title_separator' => ['required', 'string', 'max:10'],
            'default_share_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'default_meta_description' => ['nullable', 'string', 'max:1000'],
            'robots_txt' => [
                'nullable',
                'string',
                'max:5000',
                function (string $attribute, mixed $value, \Closure $fail) use ($robotsTxt): void {
                    if ($value !== null && $value !== '' && ! $robotsTxt->isValid($value)) {
                        $fail(__('baobab::admin.seo.robots_txt_invalid'));
                    }
                },
            ],
            'force_index_on_staging' => ['nullable', 'boolean'],
            'organization_type' => ['nullable', 'string', 'in:Organization,Person'],
            'social_profiles' => [
                'nullable',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (preg_split('/\r\n|\r|\n/', (string) $value) ?: [] as $line) {
                        $line = trim($line);

                        if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL) === false) {
                            $fail(__('baobab::admin.seo.social_profiles_invalid', ['url' => $line]));
                        }
                    }
                },
            ],
            'title_templates' => ['nullable', 'array'],
            'title_templates.*' => ['nullable', 'string', 'max:255'],
            'exclude_from_sitemap' => ['nullable', 'array'],
            'exclude_from_sitemap.*' => ['nullable', 'boolean'],
        ]);

        $typeSettings = [];

        foreach (ContentType::where('is_addressable', true)->pluck('key') as $key) {
            $typeSettings[$key] = [
                'title_template' => $validated['title_templates'][$key] ?? null,
                'exclude_from_sitemap' => (bool) ($validated['exclude_from_sitemap'][$key] ?? false),
            ];
        }

        app(UpdateSeoSettings::class)(
            [
                'site_name' => $validated['site_name'] ?? null,
                'title_separator' => $validated['title_separator'],
                'default_share_media_id' => $validated['default_share_media_id'] ?? null,
                'default_meta_description' => $validated['default_meta_description'] ?? null,
                'robots_txt' => $validated['robots_txt'] ?? null,
                'force_index_on_staging' => (bool) ($validated['force_index_on_staging'] ?? false),
                'organization_type' => $validated['organization_type'] ?? 'Organization',
                'social_profiles' => $validated['social_profiles'] ?? null,
            ],
            $typeSettings,
        );

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.seo.updated')]);

        return redirect()->route('admin.seo.index');
    }
}
