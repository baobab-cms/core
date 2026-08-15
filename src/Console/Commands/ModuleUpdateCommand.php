<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\UpdateModule;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;
use Illuminate\Console\Command;
use Throwable;

/**
 * Met à jour un module dont le code a changé sur disque (spec-modules §3,
 * suivi n° 156) : migrations en attente, puis resynchronisation du manifeste.
 *
 * Distincte de `module:sync`, qui ne touche qu'au manifeste — les deux étapes
 * sont distinctes dans la spec, et resynchroniser seul reste le bon geste quand
 * seul `module.json` a bougé.
 */
final class ModuleUpdateCommand extends Command
{
    protected $signature = 'module:update
        {name : The module name (vendor/slug)}
        {--force : Also remove permissions the manifest no longer declares, revoking them from roles and users}';

    protected $description = 'Run the pending migrations of an installed module, then refresh its manifest.';

    public function handle(UpdateModule $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            $this->error(ModuleNotFoundException::named($name)->getMessage());

            return self::FAILURE;
        }

        try {
            $result = $action($module, (bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report($module->name, $result);

        return self::SUCCESS;
    }

    /**
     * @param  array{migrations: list<string>, manifest: array{manifest_changed: bool, permissions: array{added: list<string>, updated: list<string>, removed: list<string>}, menu_items: int}}  $result
     */
    private function report(string $name, array $result): void
    {
        $permissions = $result['manifest']['permissions'];

        $untouched = $result['migrations'] === []
            && ! $result['manifest']['manifest_changed']
            && $permissions['added'] === []
            && $permissions['updated'] === []
            && $permissions['removed'] === [];

        if ($untouched) {
            $this->info("Module [{$name}] already up to date.");

            return;
        }

        $this->info("Module [{$name}] updated.");

        // Les migrations d'abord : c'est la moitié que `module:sync` ne fait pas,
        // donc la seule raison d'avoir choisi cette commande-ci.
        foreach ($result['migrations'] as $migration) {
            $this->line("  Migrated: {$migration}");
        }

        foreach ([
            'Permissions added' => $permissions['added'],
            'Permissions updated' => $permissions['updated'],
            'Permissions removed' => $permissions['removed'],
        ] as $label => $keys) {
            if ($keys !== []) {
                $this->line("  {$label}: ".implode(', ', $keys));
            }
        }
    }
}
