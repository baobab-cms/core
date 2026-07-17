<?php

declare(strict_types=1);

namespace Baobab\Seo\Support;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Titre brut d'une entrée via `title_field` du blueprint (repli sur la clé
 * du Content Type si non déclaré). Extrait de `ComposeSeoMeta::rawTitle()`
 * en service partagé — même patron que `FirstImageFieldResolver` (Pass C) :
 * `ComposeSeoMeta` (cascade de titre, spec 07 §2.2) et `ComposeJsonLd`
 * (jeton spécial `{title}` du mapping schema.org, spec 07 §7) ont besoin de
 * la même routine.
 */
final class EntryTitleResolver
{
    public function __invoke(ContentType $contentType, Model $entry): string
    {
        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;

        return $titleField !== null ? (string) $entry->getAttribute($titleField) : $contentType->key;
    }
}
