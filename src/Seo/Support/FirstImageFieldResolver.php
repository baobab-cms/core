<?php

declare(strict_types=1);

namespace Baobab\Seo\Support;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;

/**
 * Premier champ `image` du blueprint d'un Content Type, résolu vers son
 * `Media` sur une entrée donnée (spec 07 §2.2 : og:image saisi → premier
 * champ image du contenu → image par défaut du site ; spec 07 §5 : image
 * principale d'une URL de sitemap). Extrait en service partagé plutôt que
 * dupliqué entre `ComposeSeoMeta` et `BuildContentTypeSitemap` — c'est
 * exactement la même routine, pas seulement structurellement proche.
 */
final class FirstImageFieldResolver
{
    public function __invoke(ContentType $contentType, Model $entry): ?Media
    {
        /** @var array<int, array<string, mixed>> $fields */
        $fields = (array) ($contentType->blueprint['fields'] ?? []);

        $field = collect($fields)->first(fn (array $field): bool => ($field['type'] ?? null) === 'image');

        if ($field === null) {
            return null;
        }

        $mediaId = $entry->getAttribute((string) $field['key']);

        return $mediaId !== null ? Media::find((int) $mediaId) : null;
    }
}
