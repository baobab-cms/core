<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\System\Actions\CreateBackup;
use Illuminate\Console\Command;

/**
 * Distincte de `backup:run` natif (spec 12 §11) : passe par `CreateBackup`
 * pour que la sauvegarde soit auditée, déclenche les hooks
 * `baobab.backup.*` et applique la rétention configurée. Enregistrée dans
 * `SchedulerRegistrar` pour l'exécution planifiée quotidienne (spec §4.2),
 * la bascule « désactivable » de cette dernière s'applique uniquement à
 * la vraie tâche cron (`register()`), jamais ici : un `artisan
 * baobab:backup` explicite ou « Exécuter maintenant » créent toujours une
 * sauvegarde.
 */
final class BackupRunCommand extends Command
{
    protected $signature = 'baobab:backup';

    protected $description = 'Crée une sauvegarde complète (base + fichiers), Baobab-wrapped.';

    public function handle(CreateBackup $action): int
    {
        if (! $action()) {
            $this->error('Backup failed.');

            return self::FAILURE;
        }

        $this->info('Backup completed.');

        return self::SUCCESS;
    }
}
