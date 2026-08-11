<?php

declare(strict_types=1);

namespace Baobab\Modules;

use Baobab\Modules\Models\Module;

/**
 * Union des modules installés (table `modules`) et des modules seulement
 * découverts sur disque, pour le cycle de vie (spec-modules §3).
 *
 * Source unique et volontaire : `module:list` et l'écran admin « Modules »
 * (M8 point 9) montrent le même inventaire, calculé au même endroit — un
 * écran qui liste autre chose que la CLI serait un second chemin de code,
 * exactement ce que le principe API-first proscrit.
 */
final class ModuleInventory
{
    public function __construct(private readonly ModuleDiscovery $discovery) {}

    /**
     * @return list<ModuleInventoryEntry> Triés par type puis par titre.
     */
    public function all(): array
    {
        $discovered = $this->discovery->scan();
        $installed = Module::all()->keyBy('name');

        /** @var list<ModuleInventoryEntry> $entries */
        $entries = $installed
            ->map(fn (Module $module): ModuleInventoryEntry => new ModuleInventoryEntry(
                name: $module->name,
                title: $module->title,
                version: $module->version,
                type: $module->type,
                status: $module->status,
                source: $module->source,
                id: $module->id,
                requires: $module->requiredModules(),
                onDisk: $discovered->has($module->name),
            ))
            ->values()
            ->all();

        foreach ($discovered as $module) {
            if ($installed->has($module->manifest->name())) {
                continue;
            }

            $entries[] = new ModuleInventoryEntry(
                name: $module->manifest->name(),
                title: $module->manifest->title(),
                version: $module->manifest->version(),
                type: $module->manifest->type(),
                status: 'discovered',
                source: $module->source,
                id: null,
                requires: $module->manifest->requiresModules(),
                onDisk: true,
            );
        }

        usort(
            $entries,
            fn (ModuleInventoryEntry $a, ModuleInventoryEntry $b): int => [$a->type, $a->title] <=> [$b->type, $b->title],
        );

        return $entries;
    }
}
