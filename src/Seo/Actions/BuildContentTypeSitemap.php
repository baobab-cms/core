<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Seo\Models\SeoMeta;
use Baobab\Seo\Support\FirstImageFieldResolver;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Sitemap XML d'un Content Type adressable (spec 07 §5) — construction
 * manuelle (jamais en mode crawler) : une `Url` par entrée `published`,
 * jamais `noindex` (`SeoMeta::forEntry()`), `lastmod` = `updated_at`,
 * image principale si `FirstImageFieldResolver` en trouve une.
 *
 * Simplification assumée : pas de découpage en plusieurs fichiers au-delà
 * de 1 000 URLs (spec 07 §5) — la limite technique réelle d'un sitemap est
 * 50 000 URLs/50 Mo, largement suffisante pour tout Content Type de ce
 * projet ; `Sitemap::maxTagsPerSitemap()` de spatie/laravel-sitemap ne
 * découpe qu'à l'écriture sur disque (`writeToFile`/`writeToDisk`), pas au
 * `render()` direct utilisé ici pour servir la réponse dynamiquement.
 */
final class BuildContentTypeSitemap
{
    public function __construct(private readonly FirstImageFieldResolver $imageResolver) {}

    public function __invoke(ContentType $contentType): Sitemap
    {
        $sitemap = Sitemap::create();

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        $modelClass::query()
            ->where('status', 'published')
            ->each(function (Model $entry) use ($contentType, $sitemap): void {
                $meta = SeoMeta::forEntry($entry);

                if ($meta->robots_noindex) {
                    return;
                }

                $url = Url::create(url("/{$contentType->urlPrefix()}/{$entry->getAttribute('slug')}"))
                    ->setLastModificationDate($entry->getAttribute('updated_at') ?? now());

                $image = ($this->imageResolver)($contentType, $entry);

                if ($image !== null) {
                    $url->addImage($image->url());
                }

                $sitemap->add($url);
            });

        return $sitemap;
    }
}
