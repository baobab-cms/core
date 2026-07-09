<?php

declare(strict_types=1);

namespace Baobab\Actions\Modules;

use Baobab\Facades\Hook;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Exceptions\ModuleStillActiveException;
use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\Artisan;

/**
 * Désinstalle un module inactif (spec 01 §3). Avec --purge, rejoue le
 * rollback de ses migrations avant de supprimer la ligne modules (les
 * permissions et menus associés suivent par cascade DB).
 */
final class UninstallModule
{
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
        }

        $snapshot = $module->only(['name', 'title', 'version']);

        $module->delete();

        Hook::action('baobab.module.uninstalled', $snapshot);
    }

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
        ]);
    }
}
