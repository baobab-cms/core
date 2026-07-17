<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Baobab\Seo\Models\SeoSetting;

/**
 * Met à jour les réglages SEO globaux et les réglages par Content Type
 * (gabarit de titre, exclusion du sitemap — spec 07 §2.2, §5, écran
 * `admin/seo`). Validation faite par l'appelant — patron exact
 * `UpdateReadingSettings`.
 */
final class UpdateSeoSettings
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InvalidateSitemapCache $invalidateSitemap,
    ) {}

    /**
     * @param  array{site_name?: string|null, title_separator?: string, default_share_media_id?: int|null, default_meta_description?: string|null, robots_txt?: string|null, force_index_on_staging?: bool}  $data
     * @param  array<string, array{title_template?: string|null, exclude_from_sitemap?: bool}>  $typeSettings  Réglages par clé de Content Type.
     */
    public function __invoke(array $data, array $typeSettings = []): SeoSetting
    {
        $setting = SeoSetting::current();
        $setting->fill($data);
        $setting->save();

        foreach ($typeSettings as $contentTypeKey => $values) {
            $contentType = ContentType::where('key', $contentTypeKey)->first();

            if (! $contentType instanceof ContentType) {
                continue;
            }

            $typeSetting = SeoContentTypeSetting::forContentType($contentType);
            $typeSetting->title_template = ($values['title_template'] ?? '') !== '' ? $values['title_template'] : null;
            $typeSetting->exclude_from_sitemap = $values['exclude_from_sitemap'] ?? false;
            $typeSetting->save();
        }

        if ($typeSettings !== []) {
            $this->invalidateSitemap->full();
        }

        $this->audit->record('seo.settings.updated', $setting, $data);

        Hook::action('baobab.seo.settings.updated', $setting);

        return $setting;
    }
}
