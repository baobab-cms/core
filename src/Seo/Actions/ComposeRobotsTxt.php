<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Seo\Models\SeoSetting;

/**
 * Contenu de `robots.txt` (spec 07 §8) — la protection des environnements
 * non-production prime entièrement sur le réglage personnalisé quand elle
 * est active : ce n'est jamais combiné, sinon un `Allow: /` personnalisé
 * pourrait rouvrir ce que la protection ferme.
 */
final class ComposeRobotsTxt
{
    public function __invoke(): string
    {
        $settings = SeoSetting::current();

        if (! app()->environment('production') && ! $settings->force_index_on_staging) {
            return "User-agent: *\nDisallow: /\n";
        }

        if ($settings->robots_txt !== null && trim($settings->robots_txt) !== '') {
            return $settings->robots_txt;
        }

        $adminPath = (string) config('baobab.admin.path', 'admin');
        $sitemapUrl = url('/sitemap.xml');

        return <<<ROBOTS
        User-agent: *
        Allow: /
        Disallow: /{$adminPath}
        Sitemap: {$sitemapUrl}

        ROBOTS;
    }

    /**
     * Validation syntaxique légère (spec : « éditable... avec validation
     * syntaxique ») — pas un parseur robots.txt complet, juste de quoi
     * repérer une saisie clairement invalide : chaque ligne non vide est un
     * commentaire ou une directive connue.
     */
    public function isValid(string $content): bool
    {
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^(User-agent|Allow|Disallow|Sitemap|Crawl-delay):\s*.*$/i', $line) !== 1) {
                return false;
            }
        }

        return true;
    }
}
