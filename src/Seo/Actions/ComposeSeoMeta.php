<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Seo\Models\SeoContentTypeSetting;
use Baobab\Seo\Models\SeoMeta;
use Baobab\Seo\Models\SeoSetting;
use Baobab\Seo\Support\EntryTitleResolver;
use Baobab\Seo\Support\FirstImageFieldResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Compose les balises SEO d'une page publique selon la cascade de fallback
 * de la spec 07 §2.2 : donnée saisie (metabox) → gabarit/champ du contenu →
 * réglage global. Trois cas selon ce qu'on rend (spec 03 §3, 07 §6) :
 * une entrée (`$contentType` + `$entry`), une archive (`$contentType` seul),
 * ou une page sans Content Type concret (page d'accueil par défaut) — dans
 * ce dernier cas, seuls les réglages globaux s'appliquent.
 */
final class ComposeSeoMeta
{
    public function __construct(
        private readonly FirstImageFieldResolver $imageResolver,
        private readonly EntryTitleResolver $titleResolver,
        private readonly ComposeJsonLd $jsonld,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     description: string|null,
     *     robots_noindex: bool,
     *     robots_nofollow: bool,
     *     canonical: string,
     *     og_title: string,
     *     og_description: string|null,
     *     og_image_url: string|null,
     *     og_type: string,
     *     site_name: string,
     *     jsonld: list<array<string, mixed>>,
     * }
     */
    public function __invoke(?ContentType $contentType, ?Model $entry): array
    {
        $settings = SeoSetting::current();
        $siteName = $settings->site_name ?: (string) config('app.name', 'Baobab');

        $seo = match (true) {
            $contentType !== null && $entry !== null => $this->forEntry($contentType, $entry, $settings, $siteName),
            $contentType !== null => $this->forArchive($contentType, $settings, $siteName),
            default => $this->forSite($settings, $siteName),
        };

        // Fusion avec l'environnement (spec 07 §6, §8) : hors production,
        // noindex s'impose sur les 3 cas d'un coup, sauf dérogation
        // explicite — même règle que l'en-tête HTTP posé par
        // ForceStagingNoindexHeader, appliquée ici avant le filtre pour
        // qu'un module reste libre de la neutraliser volontairement via
        // baobab.seo.meta s'il en a vraiment besoin.
        if (! app()->environment('production') && ! $settings->force_index_on_staging) {
            $seo['robots_noindex'] = true;
        }

        $seo['jsonld'] = ($this->jsonld)($contentType, $entry);

        /** @var array{title: string, description: string|null, robots_noindex: bool, robots_nofollow: bool, canonical: string, og_title: string, og_description: string|null, og_image_url: string|null, og_type: string, site_name: string, jsonld: list<array<string, mixed>>} $filtered */
        $filtered = Hook::filter('baobab.seo.meta', $seo, $contentType, $entry);

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function forEntry(ContentType $contentType, Model $entry, SeoSetting $settings, string $siteName): array
    {
        $meta = SeoMeta::forEntry($entry);
        $rawTitle = $this->rawTitle($contentType, $entry);
        $template = SeoContentTypeSetting::forContentType($contentType)->title_template;

        $title = $this->firstNonEmpty([
            $meta->meta_title,
            $template !== null ? $this->applyTemplate($template, $rawTitle, $siteName) : null,
            $rawTitle,
        ]);

        $description = $this->firstNonEmpty([
            $meta->meta_description,
            $this->excerptFromEntry($contentType, $entry),
            $settings->default_meta_description,
        ]);

        $image = $meta->ogImage ?? ($this->imageResolver)($contentType, $entry) ?? $settings->defaultShareMedia;

        return [
            'title' => $title,
            'description' => $description,
            'robots_noindex' => $meta->robots_noindex,
            'robots_nofollow' => $meta->robots_nofollow,
            'canonical' => $meta->canonical_url ?: $this->defaultCanonical(),
            'og_title' => $this->firstNonEmpty([$meta->og_title, $title]),
            'og_description' => $this->firstNonEmpty([$meta->og_description, $description]),
            'og_image_url' => $image?->url(),
            'og_type' => 'article',
            'site_name' => $siteName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forArchive(ContentType $contentType, SeoSetting $settings, string $siteName): array
    {
        /** @var string $rawTitle */
        $rawTitle = $contentType->blueprint['label']['plural'] ?? $contentType->key;
        $template = SeoContentTypeSetting::forContentType($contentType)->title_template;

        $title = $this->firstNonEmpty([
            $template !== null ? $this->applyTemplate($template, $rawTitle, $siteName) : null,
            $rawTitle,
        ]);

        return [
            'title' => $title,
            'description' => $settings->default_meta_description,
            'robots_noindex' => false,
            'robots_nofollow' => false,
            'canonical' => $this->defaultCanonical(),
            'og_title' => $title,
            'og_description' => $settings->default_meta_description,
            'og_image_url' => $settings->defaultShareMedia?->url(),
            'og_type' => 'website',
            'site_name' => $siteName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forSite(SeoSetting $settings, string $siteName): array
    {
        return [
            'title' => $siteName,
            'description' => $settings->default_meta_description,
            'robots_noindex' => false,
            'robots_nofollow' => false,
            'canonical' => $this->defaultCanonical(),
            'og_title' => $siteName,
            'og_description' => $settings->default_meta_description,
            'og_image_url' => $settings->defaultShareMedia?->url(),
            'og_type' => 'website',
            'site_name' => $siteName,
        ];
    }

    private function rawTitle(ContentType $contentType, Model $entry): string
    {
        return ($this->titleResolver)($contentType, $entry);
    }

    private function excerptFromEntry(ContentType $contentType, Model $entry): ?string
    {
        /** @var array<int, array<string, mixed>> $fields */
        $fields = (array) ($contentType->blueprint['fields'] ?? []);

        $field = collect($fields)->first(fn (array $field): bool => in_array($field['type'] ?? null, ['richtext', 'textarea'], true));

        if ($field === null) {
            return null;
        }

        $raw = (string) ($entry->getAttribute((string) $field['key']) ?? '');

        if ($raw === '') {
            return null;
        }

        return Str::limit(trim(strip_tags($raw)), 160);
    }

    /**
     * Cascade générique : premier élément non vide (`null`/chaîne vide
     * ignorés) — patron de résolution partagé par title/description/og:*
     * (spec 07 §2.2).
     *
     * @param  list<string|null>  $candidates
     */
    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * `{title}`/`{site_name}` uniquement en Pass A (spec 07 §2.2 liste aussi
     * `{author}`/`{category}`/les champs du type — hors périmètre tant
     * qu'aucun gabarit réel ne les consomme, extensible sans redesign via
     * le filtre `baobab.seo.meta` déjà appliqué sur le résultat final).
     */
    private function applyTemplate(string $template, string $title, string $siteName): string
    {
        return str_replace(['{title}', '{site_name}'], [$title, $siteName], $template);
    }

    /**
     * Auto-référente, sans query string (spec 07 §6) — sauf pagination
     * d'archive (`?page=N`, N > 1), qui doit pointer vers elle-même plutôt
     * que collapser vers la page 1 (spec 07 §3 dernière puce).
     */
    private function defaultCanonical(): string
    {
        $url = url()->current();
        $page = (int) request()->query('page', 1);

        return $page > 1 ? "{$url}?page={$page}" : $url;
    }
}
