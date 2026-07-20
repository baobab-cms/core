<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Console\Command;

/**
 * Réindexation complète ou ciblée (spec 11 §3.3/§10) — sous le driver
 * `database` par défaut, `Laravel\Scout\Engines\DatabaseEngine::update()`
 * est un no-op (aucun index séparé maintenu, la recherche interroge
 * directement la table à l'exécution) : cette commande n'a donc aucun
 * effet visible aujourd'hui, mais reste le primitif attendu par la spec —
 * elle devient réellement utile sans changement de code le jour où
 * `SCOUT_DRIVER` bascule sur `meilisearch` (spec 11 §2.2, §11 décision 1).
 */
final class SearchReindexCommand extends Command
{
    protected $signature = 'baobab:search:reindex {--source= : Limiter à la clé d’un Content Type}';

    protected $description = 'Réindexe les Content Types cherchables (Scout).';

    public function handle(): int
    {
        /** @var string|null $source */
        $source = $this->option('source');
        $count = 0;

        foreach ($this->searchableContentTypes() as $contentType) {
            if ($source !== null && $contentType->key !== $source) {
                continue;
            }

            $modelClass = $contentType->modelClass();
            $modelClass::makeAllSearchable();
            $count++;
        }

        if ($source !== null && $count === 0) {
            $this->error("Aucun Content Type cherchable pour la clé « {$source} ».");

            return self::FAILURE;
        }

        $this->info("{$count} Content Type(s) réindexé(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<ContentType>
     */
    private function searchableContentTypes(): array
    {
        return array_values(ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->searchableFields() !== [])
            ->all());
    }
}
