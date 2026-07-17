<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Spatie\Sitemap\SitemapIndex;

/**
 * Index des sitemaps par Content Type adressable (spec 07 §5) — une entrée
 * par type non exclu, vers `/sitemaps/{url_prefix}.xml`. Extensible : le
 * filtre `baobab.seo.sitemap.sources` (spec) reçoit la liste des URLs de
 * sous-sitemaps déjà construite, un module peut y ajouter son propre
 * endpoint (ex. un module d'annuaire avec ses propres URLs hors pipeline
 * Content Types).
 */
final class BuildSitemapIndex
{
    public function __invoke(): SitemapIndex
    {
        $index = SitemapIndex::create();

        /** @var list<string> $urls */
        $urls = ContentType::where('is_addressable', true)
            ->get()
            ->reject(fn (ContentType $contentType): bool => SeoContentTypeSetting::forContentType($contentType)->exclude_from_sitemap)
            ->map(fn (ContentType $contentType): string => url("/sitemaps/{$contentType->urlPrefix()}.xml"))
            ->values()
            ->all();

        /** @var list<string> $urls */
        $urls = Hook::filter('baobab.seo.sitemap.sources', $urls);

        foreach ($urls as $url) {
            $index->add($url);
        }

        return $index;
    }
}
