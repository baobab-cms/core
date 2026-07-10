<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\InstallModule;
use Illuminate\Console\Command;
use Throwable;

final class ModuleInstallCommand extends Command
{
    protected $signature = 'module:install {name : The module name (vendor/slug)}';

    protected $description = 'Install a discovered module: validate manifest, run migrations, register permissions and menus.';

    public function handle(InstallModule $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $module = $action($name);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module [{$module->name}] v{$module->version} installed successfully.");

        return self::SUCCESS;
    }
}
