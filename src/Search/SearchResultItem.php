<?php

declare(strict_types=1);

namespace Baobab\Search;

/**
 * Un résultat individuel (spec 11 §4.1/§4.2). `sourceKey`/`sourceLabel`
 * ajoutés en Pass B (extension anticipée par le docblock d'origine) : le
 * regroupement par source de l'omnibox et la sérialisation de
 * `GET /api/v1/search` en ont besoin sur chaque item, pas seulement sur le
 * groupe — un consommateur peut aplatir les groupes sans perdre l'origine.
 */
final readonly class SearchResultItem
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $excerpt = null,
        public ?string $sourceKey = null,
        public ?string $sourceLabel = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'excerpt' => $this->excerpt,
            'source' => $this->sourceKey,
            'source_label' => $this->sourceLabel,
        ];
    }
}
