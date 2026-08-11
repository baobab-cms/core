<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\ModuleStillActiveException;
use Baobab\Modules\Models\Module;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

/**
 * Désinstalle un module inactif (spec 01 §3). Avec --purge, rejoue le
 * rollback de ses migrations avant de supprimer la ligne modules (les
 * permissions et menus associés suivent par cascade DB).
 */
final class UninstallModule
{
    public function __construct(private readonly Migrator $migrator) {}

    public function __invoke(string $name, bool $purge = false): void
    {
        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            throw ModuleNotFoundException::named($name);
        }

        if ($module->status === 'active') {
            throw ModuleStillActiveException::named($name);
        }

        if ($purge) {
            $this->rollbackMigrations($module->path);
            $this->purgePermissions($module);
        }

        $snapshot = $module->only(['name', 'title', 'version']);

        $module->delete();

        Hook::action('baobab.module.uninstalled', $snapshot);
    }

    /**
     * `migrate:rollback` ne regarde par défaut que le **dernier lot**. Les
     * migrations d'un module installé avant que quoi que ce soit d'autre ne
     * migre n'y sont plus : sans `--step`, la purge ne défaisait rien du tout,
     * et en silence — les tables du module restaient en base alors que la CLI
     * comme l'écran annonçaient leur suppression. Défaut relevé le 10 août
     * 2026 en vérification navigateur (M8 point 9, Pass A).
     *
     * On demande donc autant d'étapes qu'il y a de migrations jouées. Laravel
     * ignore celles qui ne se trouvent pas dans `--path` (« Migration not
     * found »), ce qui laisse exactement les migrations de ce module, quel que
     * soit le lot dans lequel elles ont été jouées.
     */
    private function rollbackMigrations(string $path): void
    {
        $migrationsPath = $path.'/database/migrations';

        if (! is_dir($migrationsPath)) {
            return;
        }

        Artisan::call('migrate:rollback', [
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
            '--step' => count($this->migrator->getRepository()->getRan()),
        ]);
    }

    private function purgePermissions(Module $module): void
    {
        $keys = $module->permissions->pluck('key');

        if ($keys->isEmpty()) {
            return;
        }

        Permission::whereIn('name', $keys)->where('guard_name', 'baobab')->delete();
    }
}
