<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Seo\Actions\InvalidateSitemapCache;
use Baobab\Seo\Actions\RenderContentTypeSitemap;
use Baobab\Seo\Actions\RenderSitemapIndex;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Illuminate\Console\Command;

/**
 * Régénère le cache de tous les sitemaps (spec 07 §5, nom de commande
 * exact) — invalide puis réchauffe immédiatement, plutôt que de se
 * contenter d'invalider et laisser la première visite payer le coût.
 */
final class SeoSitemapCommand extends Command
{
    protected $signature = 'seo:sitemap';

    protected $description = 'Régénère le sitemap XML (index + un par Content Type adressable).';

    public function handle(InvalidateSitemapCache $invalidate, RenderSitemapIndex $renderIndex, RenderContentTypeSitemap $renderType): int
    {
        $invalidate->full();
        $renderIndex();

        $count = 0;

        foreach (ContentType::where('is_addressable', true)->get() as $contentType) {
            if (SeoContentTypeSetting::forContentType($contentType)->exclude_from_sitemap) {
                continue;
            }

            $renderType($contentType->urlPrefix());
            $count++;
        }

        $this->info("Sitemap régénéré : index + {$count} type(s).");

        return self::SUCCESS;
    }
}
