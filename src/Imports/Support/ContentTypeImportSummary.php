<?php

declare(strict_types=1);

namespace Baobab\Imports\Support;

/**
 * Résultat d'import pour un content type de l'archive (spec 12 §5.3, cadrage
 * Pass F2, suivi n° 328). `willCreate` distingue un type absent localement
 * (créé à la volée, `created`/`updated`/`skipped`/`duplicated` toujours à 0 —
 * un type qui n'existait pas n'a par construction aucune ligne à comparer,
 * §12 décision 11) d'un type déjà présent (la stratégie de conflit s'y
 * applique réellement).
 */
final readonly class ContentTypeImportSummary
{
    public function __construct(
        public string $key,
        public bool $willCreate,
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $duplicated = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'will_create' => $this->willCreate,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'duplicated' => $this->duplicated,
        ];
    }
}
