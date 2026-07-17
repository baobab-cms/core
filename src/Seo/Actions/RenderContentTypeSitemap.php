<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sert le sitemap XML d'un Content Type adressable (spec 07 §5),
 * `$prefix` étant son `url_prefix` public (même segment que l'URL de son
 * archive) — 404 si inconnu, non adressable, ou explicitement exclu du
 * sitemap. Mis en cache par type, invalidé par `InvalidateSitemapCache`.
 */
final class RenderContentTypeSitemap
{
    public function __construct(private readonly BuildContentTypeSitemap $build) {}

    public function __invoke(string $prefix): Response
    {
        $contentType = ContentType::where('is_addressable', true)
            ->get()
            ->first(fn (ContentType $type): bool => $type->urlPrefix() === $prefix);

        if ($contentType === null || SeoContentTypeSetting::forContentType($contentType)->exclude_from_sitemap) {
            throw new NotFoundHttpException;
        }

        $xml = Cache::remember(
            self::cacheKey($contentType->key),
            now()->addHours(6),
            fn (): string => ($this->build)($contentType)->render()
        );

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    public static function cacheKey(string $contentTypeKey): string
    {
        return "baobab.sitemap.type.{$contentTypeKey}";
    }
}
