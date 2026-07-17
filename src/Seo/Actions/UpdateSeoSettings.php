<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Baobab\Seo\Models\SeoSetting;

/**
 * Met à jour les réglages SEO globaux et les gabarits de titre par Content
 * Type (spec 07 §2.2, écran `admin/seo`). Validation faite par l'appelant —
 * patron exact `UpdateReadingSettings`.
 */
final class UpdateSeoSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{site_name?: string|null, title_separator?: string, default_share_media_id?: int|null, default_meta_description?: string|null}  $data
     * @param  array<string, string|null>  $titleTemplates  Gabarit par clé de Content Type.
     */
    public function __invoke(array $data, array $titleTemplates = []): SeoSetting
    {
        $setting = SeoSetting::current();
        $setting->fill($data);
        $setting->save();

        foreach ($titleTemplates as $contentTypeKey => $template) {
            $contentType = ContentType::where('key', $contentTypeKey)->first();

            if (! $contentType instanceof ContentType) {
                continue;
            }

            $typeSetting = SeoContentTypeSetting::forContentType($contentType);
            $typeSetting->title_template = $template !== '' ? $template : null;
            $typeSetting->save();
        }

        $this->audit->record('seo.settings.updated', $setting, $data);

        Hook::action('baobab.seo.settings.updated', $setting);

        return $setting;
    }
}
