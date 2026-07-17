<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Baobab\Seo\SeoContext;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\View\Component;

/**
 * `<x-baobab::seo-head />` (spec 03 §3, 07 §6) — remplace le stub volontaire
 * de M6 point 2. Posé tel quel, sans attribut, dans le layout d'un thème :
 * lit `SeoContext`, rempli par le Render Action juste avant `view(...)->render()`
 * (aucune transmission explicite requise, cf. docblock `SeoContext`). Si le
 * contexte est vide (page hors pipeline SEO — ex. un thème qui appelle le
 * composant en dehors du rendu public), repli minimal sur `config('app.name')`
 * plutôt qu'un `<head>` cassé.
 */
final class SeoHead extends Component
{
    public string $title;

    public ?string $description;

    public bool $robotsNoindex;

    public bool $robotsNofollow;

    public ?string $canonical;

    public string $ogTitle;

    public ?string $ogDescription;

    public ?string $ogImageUrl;

    public string $ogType;

    public string $siteName;

    public function __construct(SeoContext $context)
    {
        $seo = $context->get();

        $this->title = $seo['title'] ?? (string) config('app.name', 'Baobab');
        $this->description = $seo['description'] ?? null;
        $this->robotsNoindex = $seo['robots_noindex'] ?? false;
        $this->robotsNofollow = $seo['robots_nofollow'] ?? false;
        $this->canonical = $seo['canonical'] ?? null;
        $this->ogTitle = $seo['og_title'] ?? $this->title;
        $this->ogDescription = $seo['og_description'] ?? $this->description;
        $this->ogImageUrl = $seo['og_image_url'] ?? null;
        $this->ogType = $seo['og_type'] ?? 'website';
        $this->siteName = $seo['site_name'] ?? (string) config('app.name', 'Baobab');
    }

    public function render(): ViewContract
    {
        return view('baobab::components.seo-head');
    }
}
