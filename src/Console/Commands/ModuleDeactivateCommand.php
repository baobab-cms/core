<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\DeactivateModule;
use Illuminate\Console\Command;
use Throwable;

final class ModuleDeactivateCommand extends Command
{
    protected $signature = 'module:deactivate {name : The module name (vendor/slug)}';

    protected $description = 'Deactivate an active module. Data is preserved.';

    public function handle(DeactivateModule $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $module = $action($name);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module [{$module->name}] deactivated.");

        return self::SUCCESS;
    }
}
