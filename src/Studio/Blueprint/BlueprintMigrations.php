<?php

declare(strict_types=1);

namespace Baobab\Studio\Blueprint;

use Baobab\Facades\Hook;

/**
 * Registre des migrateurs de blueprint (spec 01 §7.1 décision 5) : un
 * blueprint dont `blueprint_version` est antérieur à la version courante est
 * migré automatiquement au chargement d'un brouillon, avec notification
 * (`baobab.studio.blueprint_migrated`). v1 : il n'existe encore qu'une seule
 * version de format — le registre est vide, prêt à être alimenté via
 * `register()` par les évolutions futures du format sans toucher au reste du
 * Studio.
 */
final class BlueprintMigrations
{
    /** @var array<int, class-string<BlueprintMigrator>> */
    private array $migrators = [];

    /**
     * @param  class-string<BlueprintMigrator>  $migrator
     */
    public function register(int $fromVersion, string $migrator): void
    {
        $this->migrators[$fromVersion] = $migrator;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function migrate(array $data): array
    {
        $version = (int) ($data['blueprint_version'] ?? 1);
        $migrated = false;

        while (isset($this->migrators[$version])) {
            $data = app($this->migrators[$version])->migrate($data);
            $newVersion = (int) ($data['blueprint_version'] ?? $version + 1);
            $migrated = true;

            if ($newVersion <= $version) {
                break;
            }

            $version = $newVersion;
        }

        if ($migrated) {
            Hook::action('baobab.studio.blueprint_migrated', $data);
        }

        return $data;
    }
}
