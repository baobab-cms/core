<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\SyncModuleManifest;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;
use Illuminate\Console\Command;
use Throwable;

/**
 * Relit `module.json` d'un module installé et remet à jour ce que Baobab en
 * avait retenu (suivi n° 111, Pass B).
 *
 * L'appelant terminal du trio : c'est celui qui sert le cas que ni le Studio ni
 * l'admin ne couvrent naturellement — un module mis à jour par `composer update`,
 * dont le manifeste a changé sur disque sans que rien ne le signale.
 */
final class ModuleSyncCommand extends Command
{
    protected $signature = 'module:sync
        {name : The module name (vendor/slug)}
        {--force : Also remove permissions the manifest no longer declares, revoking them from roles and users}';

    protected $description = 'Re-read an installed module manifest and refresh its permissions, menus and declarations.';

    public function handle(SyncModuleManifest $action): int
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
     * Dire ce qui a changé plutôt qu'un « Terminé ✓ » : sans ce détail, une
     * resynchronisation qui n'a rien trouvé et une qui a révoqué trois
     * permissions se ressemblent trait pour trait.
     *
     * @param  array{manifest_changed: bool, permissions: array{added: list<string>, updated: list<string>, removed: list<string>}, menu_items: int}  $result
     */
    private function report(string $name, array $result): void
    {
        $permissions = $result['permissions'];
        $untouched = ! $result['manifest_changed']
            && $permissions['added'] === []
            && $permissions['updated'] === []
            && $permissions['removed'] === [];

        if ($untouched) {
            $this->info("Module [{$name}] already up to date.");

            return;
        }

        $this->info("Module [{$name}] synced.");

        foreach ([
            'Permissions added' => $permissions['added'],
            'Permissions updated' => $permissions['updated'],
            'Permissions removed' => $permissions['removed'],
        ] as $label => $keys) {
            if ($keys !== []) {
                $this->line("  {$label}: ".implode(', ', $keys));
            }
        }

        $this->line("  Menu items: {$result['menu_items']}");
    }
}
