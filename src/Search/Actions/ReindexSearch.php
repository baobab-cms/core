<?php

declare(strict_types=1);

namespace Baobab\Search\Actions;

use Baobab\ContentTypes\Models\ContentType;

/**
 * Réindexe les Content Types cherchables (spec 11 §3.3) — extraite de
 * `SearchReindexCommand` en Pass B pour être partagée avec l'écran
 * `admin/search` (règle API-first n° 1 : la CLI et l'admin sont deux
 * adaptateurs du même code). Sans effet visible sous le driver `database`
 * (aucun index séparé, cf. suivi n° 78) — devient réellement utile à la
 * bascule Meilisearch, sans changement ici.
 *
 * @return int Nombre de Content Types réindexés.
 */
final class ReindexSearch
{
    public function __invoke(?string $source = null): int
    {
        $count = 0;

        foreach ($this->searchableContentTypes() as $contentType) {
            if ($source !== null && $contentType->key !== $source) {
                continue;
            }

            $modelClass = $contentType->modelClass();
            $modelClass::makeAllSearchable();
            $count++;
        }

        return $count;
    }

    /**
     * @return list<ContentType>
     */
    public function searchableContentTypes(): array
    {
        return array_values(ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->searchableFields() !== [])
            ->all());
    }
}
