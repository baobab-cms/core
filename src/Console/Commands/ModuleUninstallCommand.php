<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\UninstallModule;
use Illuminate\Console\Command;
use Throwable;

final class ModuleUninstallCommand extends Command
{
    protected $signature = 'module:uninstall
        {name : The module name (vendor/slug)}
        {--purge : Roll back module migrations and delete all its data}
        {--delete-files : Also delete the module source files from disk (local modules only)}';

    protected $description = 'Uninstall an inactive module. Use --purge to also roll back its migrations.';

    public function handle(UninstallModule $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');
        $purge = (bool) $this->option('purge');
        $deleteFiles = (bool) $this->option('delete-files');

        if ($purge && ! $this->confirm("This will roll back all migrations for [{$name}] and destroy its data. Proceed?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        if ($deleteFiles && ! $this->confirm("This will delete the source files of [{$name}] from disk. Proceed?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        try {
            $action($name, $purge, $deleteFiles);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $suffixes = array_filter([
            $purge ? 'data purged' : null,
            $deleteFiles ? 'files deleted' : null,
        ]);

        $this->info("Module [{$name}] uninstalled".($suffixes !== [] ? ' ('.implode(', ', $suffixes).')' : '').'.');

        return self::SUCCESS;
    }
}
