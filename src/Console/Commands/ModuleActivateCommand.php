<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Actions\Modules\ActivateModule;
use Illuminate\Console\Command;
use Throwable;

final class ModuleActivateCommand extends Command
{
    protected $signature = 'module:activate {name : The module name (vendor/slug)}';

    protected $description = 'Activate an installed module.';

    public function handle(ActivateModule $action): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $module = $action($name);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module [{$module->name}] activated.");

        return self::SUCCESS;
    }
}
