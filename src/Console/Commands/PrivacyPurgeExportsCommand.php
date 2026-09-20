<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Privacy\Actions\PurgeExpiredPrivacyExports;
use Illuminate\Console\Command;

/**
 * Purge programmée des archives d'export échues (spec 16 §4.1) — enregistrée
 * quotidiennement dans le `SchedulerRegistrar`.
 */
final class PrivacyPurgeExportsCommand extends Command
{
    protected $signature = 'baobab:privacy:purge-exports';

    protected $description = 'Détruit les archives d\'export de données personnelles échues, et leur mot de passe non lu.';

    public function handle(PurgeExpiredPrivacyExports $purge): int
    {
        $this->info(__('baobab::privacy.export.purged', ['count' => $purge()]));

        return self::SUCCESS;
    }
}
