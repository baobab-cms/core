<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Console\Command;

/**
 * État de la recherche (spec 11 §4.3/§10) — driver actif, volume par
 * Content Type cherchable. Sous le driver `database`, il n'existe aucun
 * index séparé (voir `SearchReindexCommand`) : le « volume » rapporté est
 * le nombre de lignes de la table elle-même, honnête plutôt qu'un faux
 * compteur d'« indexés » qui n'existe pas pour ce driver.
 */
final class SearchStatusCommand extends Command
{
    protected $description = 'Affiche le driver de recherche actif et le volume par Content Type cherchable.';

    protected $signature = 'baobab:search:status';

    public function handle(): int
    {
        $driver = (string) config('scout.driver');

        $this->info("Driver actif : {$driver}");

        $rows = ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->searchableFields() !== [])
            ->map(fn (ContentType $contentType): array => [
                $contentType->key,
                count($contentType->searchableFields()),
                $contentType->modelClass()::query()->count(),
            ])
            ->all();

        $this->table(['Content Type', 'Champs cherchables', 'Volume'], $rows);

        return self::SUCCESS;
    }
}
