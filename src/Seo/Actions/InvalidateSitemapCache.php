<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Facades\Cache;

/**
 * Invalide le cache des sitemaps (spec 07 §5 : « cache invalidé à la
 * publication/modification »). `forType()` — un contenu sauvegardé/transitionné
 * ne rend obsolète que le sitemap de son propre type, jamais l'index (la
 * liste des types éligibles ne change pas). `full()` — une bascule
 * `exclude_from_sitemap` peut faire apparaître/disparaître un type de
 * l'index en plus de son propre sitemap ; `seo:sitemap` régénère tout sans
 * attendre l'expiration naturelle du cache (6 h, cf. `RenderSitemapIndex`/
 * `RenderContentTypeSitemap`).
 */
final class InvalidateSitemapCache
{
    public function forType(string $contentTypeKey): void
    {
        Cache::forget(RenderContentTypeSitemap::cacheKey($contentTypeKey));
    }

    public function full(): void
    {
        Cache::forget(RenderSitemapIndex::CACHE_KEY);

        foreach (ContentType::where('is_addressable', true)->pluck('key') as $key) {
            $this->forType($key);
        }
    }
}
