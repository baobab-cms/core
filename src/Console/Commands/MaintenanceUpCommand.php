<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\System\Actions\ToggleMaintenanceMode;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

final class MaintenanceUpCommand extends Command
{
    protected $signature = 'baobab:up';

    protected $description = 'Bring the application out of maintenance mode (Baobab-wrapped).';

    public function handle(ToggleMaintenanceMode $action, Application $app): int
    {
        if (! $app->maintenanceMode()->active()) {
            $this->info('Application is already up.');

            return self::SUCCESS;
        }

        try {
            $action->deactivate();
        } catch (Throwable $e) {
            $this->error('Failed to disable maintenance mode: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Application is now live.');

        return self::SUCCESS;
    }
}
