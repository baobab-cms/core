<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Modules\Exceptions\InvalidManifestException;
use Illuminate\Support\Collection;

/**
 * Scanne les emplacements configurés (baobab.modules.paths) à la recherche de
 * module.json (spec 01 §7.1 : /modules et /themes en local, vendor/ pour les
 * installations Composer, priorité au local en cas de doublon de nom).
 *
 * N'est jamais appelée dans le chemin chaud d'une requête — seulement par les
 * commandes CLI et les Actions d'installation. Le chargement des providers
 * actifs à chaque requête est une lecture DB, pas un scan disque.
 */
final class ModuleDiscovery
{
    /**
     * @param  array<string, list<string>>  $paths  Source (local|composer, ordre = priorité) → glob patterns.
     */
    public function __construct(private readonly array $paths) {}

    /**
     * @return Collection<string, DiscoveredModule> Clé = nom du module (vendor/slug).
     */
    public function scan(): Collection
    {
        $discovered = new Collection;

        foreach ($this->paths as $source => $patterns) {
            foreach ($patterns as $pattern) {
                foreach ($this->matchingDirectories($pattern) as $directory) {
                    $module = $this->readModule($directory, (string) $source);

                    if ($module === null || $discovered->has($module->manifest->name())) {
                        continue;
                    }

                    $discovered->put($module->manifest->name(), $module);
                }
            }
        }

        return $discovered;
    }

    /**
     * @return list<string>
     */
    private function matchingDirectories(string $pattern): array
    {
        $matches = glob($pattern, GLOB_ONLYDIR);

        return $matches !== false ? $matches : [];
    }

    private function readModule(string $directory, string $source): ?DiscoveredModule
    {
        $manifestPath = $directory.'/module.json';

        if (! is_file($manifestPath)) {
            return null;
        }

        $json = file_get_contents($manifestPath);

        if ($json === false) {
            return null;
        }

        try {
            $manifest = ModuleManifest::fromJson($json);
        } catch (InvalidManifestException) {
            // Découverte résiliente : un manifest cassé ne doit pas empêcher
            // de lister les autres modules. La validation stricte reste
            // appliquée à l'installation (InstallModule).
            return null;
        }

        return new DiscoveredModule($manifest, $directory, $source);
    }
}
