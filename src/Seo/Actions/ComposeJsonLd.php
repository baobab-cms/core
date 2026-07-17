<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Branding\Models\BrandingSetting;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Media\Models\Media;
use Baobab\Seo\Models\SeoSetting;
use Baobab\Seo\Support\EntryTitleResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Compose le graphe de données structurées JSON-LD d'une page (spec 07 §7) :
 * `Organization`/`Person` + `WebSite` sur toute page, `BreadcrumbList` et
 * mapping schema.org optionnel du blueprint sur une entrée. Retourne la
 * liste de nœuds d'un `@graph` — `<x-baobab::seo-head />` (via `SeoContext`,
 * rempli par `ComposeSeoMeta`) l'encode dans un unique `<script
 * type="application/ld+json">`.
 *
 * `SearchAction` (spec 07 §7 : « WebSite avec SearchAction ») est omis —
 * nécessite un endpoint de recherche (spec 11, M7 point 5), pas encore
 * construit. Le mapping schema.org par Content Type reste une clé de
 * blueprint écrite à la main (`ContentTypeBlueprint::seoSchema()`) : le
 * wizard Studio qui le proposerait à la création (spec 07 §7) est M8,
 * inexistant — cohérent avec l'absence totale d'UI de blueprint à ce jour.
 */
final class ComposeJsonLd
{
    public function __construct(private readonly EntryTitleResolver $titleResolver) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(?ContentType $contentType, ?Model $entry): array
    {
        $settings = SeoSetting::current();
        $siteName = $settings->site_name ?: (string) config('app.name', 'Baobab');

        $nodes = [
            $this->organizationNode($settings, $siteName),
            $this->websiteNode($siteName),
        ];

        if ($contentType !== null && $entry !== null) {
            $nodes[] = $this->breadcrumbNode($contentType, $entry, $siteName);

            $schemaNode = $this->schemaMappingNode($contentType, $entry);

            if ($schemaNode !== null) {
                $nodes[] = $schemaNode;
            }
        }

        /** @var list<array<string, mixed>> $filtered */
        $filtered = Hook::filter('baobab.seo.jsonld', $nodes, $contentType, $entry);

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationNode(SeoSetting $settings, string $siteName): array
    {
        $node = [
            '@type' => $settings->organization_type,
            'name' => $siteName,
            'url' => url('/'),
        ];

        $logo = BrandingSetting::current()->logo;

        if ($logo !== null) {
            $node['logo'] = $logo->url();
        }

        $sameAs = collect(explode("\n", (string) $settings->social_profiles))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();

        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteNode(string $siteName): array
    {
        return [
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => url('/'),
        ];
    }

    /**
     * Accueil → archive du type → entrée (spec 07 §7).
     *
     * @return array<string, mixed>
     */
    private function breadcrumbNode(ContentType $contentType, Model $entry, string $siteName): array
    {
        /** @var string $label */
        $label = $contentType->blueprint['label']['plural'] ?? $contentType->key;

        $items = [
            ['name' => $siteName, 'url' => url('/')],
            ['name' => $label, 'url' => url("/{$contentType->urlPrefix()}")],
            ['name' => ($this->titleResolver)($contentType, $entry), 'url' => url("/{$contentType->urlPrefix()}/{$entry->getAttribute('slug')}")],
        ];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function schemaMappingNode(ContentType $contentType, Model $entry): ?array
    {
        /** @var array{type?: string, properties?: array<string, mixed>}|null $schema */
        $schema = $contentType->blueprint['seo']['schema'] ?? null;

        if ($schema === null || ($schema['type'] ?? '') === '') {
            return null;
        }

        $node = ['@type' => $schema['type']];

        foreach ((array) ($schema['properties'] ?? []) as $key => $value) {
            $node[$key] = $this->resolveValue($value, $contentType, $entry);
        }

        return $node;
    }

    private function resolveValue(mixed $value, ContentType $contentType, Model $entry): mixed
    {
        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item) => $this->resolveValue($item, $contentType, $entry))->all();
        }

        if (! is_string($value) || ! Str::isMatch('/^\{[a-z_][a-z0-9_]*\}$/', $value)) {
            return $value;
        }

        $field = substr($value, 1, -1);

        return $this->resolveField($field, $contentType, $entry);
    }

    private function resolveField(string $field, ContentType $contentType, Model $entry): mixed
    {
        if ($field === 'title') {
            return ($this->titleResolver)($contentType, $entry);
        }

        /** @var list<array<string, mixed>> $fields */
        $fields = (array) ($contentType->blueprint['fields'] ?? []);
        $definition = collect($fields)->firstWhere('key', $field);

        if ($definition === null) {
            return null;
        }

        $raw = $entry->getAttribute($field);

        return match ($definition['type'] ?? null) {
            'image' => $this->resolveMediaUrl($raw),
            'integer' => $raw !== null ? (int) $raw : null,
            'decimal' => $raw !== null ? (float) $raw : null,
            'boolean' => (bool) $raw,
            'richtext' => $raw !== null ? trim(strip_tags((string) $raw)) : null,
            default => $raw !== null ? (string) $raw : null,
        };
    }

    private function resolveMediaUrl(mixed $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        return Media::find((int) $mediaId)?->url();
    }
}
