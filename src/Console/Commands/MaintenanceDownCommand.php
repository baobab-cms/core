<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\System\Actions\ToggleMaintenanceMode;
use Illuminate\Console\Command;
use Throwable;

/**
 * Distincte du `down` natif de Laravel (spec 12 §11) : passe par
 * `ToggleMaintenanceMode` pour que l'activation soit auditée, déclenche les
 * hooks `baobab.maintenance.*` et rende `baobab::errors.maintenance` plutôt
 * que la vue générique. Le staff qui utiliserait `artisan down` directement
 * contournerait toute cette couche.
 */
final class MaintenanceDownCommand extends Command
{
    protected $signature = 'baobab:down
        {--redirect= : The path that users should be redirected to}
        {--retry= : The number of seconds after which the request may be retried}
        {--secret= : The secret phrase that may be used to bypass maintenance mode}
        {--with-secret : Generate a random secret phrase that may be used to bypass maintenance mode}
        {--status=503 : The status code that should be used when returning the maintenance mode response}';

    protected $description = 'Put the application into maintenance mode (Baobab-wrapped).';

    public function handle(ToggleMaintenanceMode $action): int
    {
        try {
            $secret = $action->activate([
                'redirect' => $this->option('redirect'),
                'retry' => $this->option('retry'),
                'secret' => $this->option('secret'),
                'withSecret' => (bool) $this->option('with-secret'),
                'status' => (int) $this->option('status'),
            ]);
        } catch (Throwable $e) {
            $this->error('Failed to enter maintenance mode: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Application is now in maintenance mode.');

        if ($secret !== null) {
            $this->info('You may bypass maintenance mode via ['.config('app.url')."/{$secret}].");
        }

        return self::SUCCESS;
    }
}
