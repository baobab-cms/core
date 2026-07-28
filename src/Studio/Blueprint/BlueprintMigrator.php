<?php

declare(strict_types=1);

namespace Baobab\Studio\Blueprint;

/**
 * Migre un blueprint de module d'une version vers la suivante (spec 01 §7.1
 * décision 5). Un migrateur se déclare responsable d'UNE version de départ
 * (enregistré dans `BlueprintMigrations` sous cette version) et doit laisser
 * `blueprint_version` à jour dans le tableau retourné.
 */
interface BlueprintMigrator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function migrate(array $data): array;
}
