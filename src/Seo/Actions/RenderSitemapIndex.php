<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Sert l'index des sitemaps (spec 07 §5), mis en cache — invalidé par
 * `InvalidateSitemapCache` (bascule `exclude_from_sitemap`) ou
 * `seo:sitemap` (régénération manuelle), jamais reconstruit à chaque hit.
 */
final class RenderSitemapIndex
{
    public const CACHE_KEY = 'baobab.sitemap.index';

    public function __construct(private readonly BuildSitemapIndex $build) {}

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addHours(6), fn (): string => ($this->build)()->render());

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}
