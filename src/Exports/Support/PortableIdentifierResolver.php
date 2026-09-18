<?php

declare(strict_types=1);

namespace Baobab\Exports\Support;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;

/**
 * Identifiant portable dual d'une entrée exportée (spec 12 §5.1, §12
 * décision 5bis) : le `slug` pour un type adressable, l'`uuid` pour un type
 * non-adressable — jamais l'`id` auto-incrémenté, qui n'a de sens que sur
 * l'instance qui l'a produit. Même logique pour un média référencé
 * (`Media::$uuid`, déjà l'identifiant stable du fichier dans l'archive).
 */
final class PortableIdentifierResolver
{
    public function forEntry(ContentType $contentType, Model $entry): string
    {
        return (string) ($contentType->is_addressable ? $entry->getAttribute('slug') : $entry->getAttribute('uuid'));
    }

    public function forMedia(?int $mediaId): ?string
    {
        if ($mediaId === null) {
            return null;
        }

        return Media::query()->find($mediaId)?->uuid;
    }

    /**
     * Résout la cible d'une relation `belongsTo` (spec 02 §5) vers son
     * identifiant portable. Ne retourne `null` que dans deux cas distincts,
     * délibérément non distingués ici (le second est le cas normal, pas une
     * erreur) : la relation pointe vers un modèle Core (`User`, jamais un
     * Content Type — les utilisateurs sont exclus de l'export par défaut,
     * spec §5.2) et n'a donc aucune ligne dans `content_types` ; ou la ligne
     * ciblée n'existe plus (FK laissée orpheline).
     */
    public function forRelationTarget(string $targetContentTypeKey, int $relatedId): ?string
    {
        $target = ContentType::where('key', $targetContentTypeKey)->first();

        if ($target === null) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $target->modelClass();
        $related = $modelClass::find($relatedId);

        if ($related === null) {
            return null;
        }

        return $this->forEntry($target, $related);
    }
}
