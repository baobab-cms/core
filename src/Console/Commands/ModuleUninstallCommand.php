<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\UninstallModule;
use Illuminate\Console\Command;
use Throwable;

final class ModuleUninstallCommand extends Command
{
    protected $signature = 'module:uninstall {name : The module name (vendor/slug)} {--purge : Roll back module migrations and delete all its data}';

    protected $description = 'Uninstall an inactive module. Use --purge to also roll back its migrations.';

    public function handle(UninstallModule $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');
        $purge = (bool) $this->option('purge');

        if ($purge && ! $this->confirm("This will roll back all migrations for [{$name}] and destroy its data. Proceed?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        try {
            $action($name, $purge);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module [{$name}] uninstalled".($purge ? ' (data purged)' : '').'.');

        return self::SUCCESS;
    }
}
