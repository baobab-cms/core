<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Privacy\Actions\DispatchDueErasures;
use Illuminate\Console\Command;

/**
 * Tâche planifiée horaire (spec 16 §4.3, §7) : met en file les demandes
 * d'effacement arrivées à échéance. Enregistrée dans le `SchedulerRegistrar`.
 */
final class PrivacyRunDueErasuresCommand extends Command
{
    protected $signature = 'baobab:privacy:run-due-erasures';

    protected $description = 'Met en file les effacements de données personnelles arrivés à échéance.';

    public function handle(DispatchDueErasures $dispatch): int
    {
        $this->info(__('baobab::privacy.erasure.dispatched', ['count' => $dispatch()]));

        return self::SUCCESS;
    }
}
