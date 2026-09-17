<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Backups\Models\BackupSetting;
use Baobab\Facades\Hook;
use Baobab\Notify\Notifier;
use Baobab\Support\Logger;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Artisan;

/**
 * Crée une sauvegarde (spec 12 §4.1, §4.2) — wrappe `backup:run` puis
 * `backup:clean` de spatie/laravel-backup, jamais réimplémenté : dump
 * MySQL + fichiers (médias, thèmes actifs) en une seule archive .zip par
 * destination, patron `RetryFailedJobs` (Artisan::call, jamais un appel
 * direct au package depuis l'appelant). `backup:run` renvoie son propre
 * code de sortie (0/1, `Spatie\Backup\Commands\BackupCommand::handle()`),
 * relu tel quel plutôt qu'un test fragile sur la sortie texte.
 *
 * Source figée au périmètre spec §4.1 — jamais `base_path()` entier (le
 * défaut du package), qui embarquerait `vendor/`/`.git/` en plus
 * d'exclure `.env` par construction plutôt que par exclusion explicite.
 *
 * Un échec (spec §4.3) journalise en trois endroits distincts, jamais
 * substituables l'un à l'autre : l'audit (donnée métier requêtable,
 * `baobab.audit.*`), le journal technique (`Baobab\Support\Logger`, seul
 * point d'entrée du channel `baobab`, spec §9) et une notification aux
 * porteurs de `.create` (patron exact `DeliverWebhook::failed()`).
 */
final class CreateBackup
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Logger $logger,
        private readonly Notifier $notifier,
    ) {}

    public function __invoke(): bool
    {
        $this->configureBackupPackage(BackupSetting::current());

        $success = Artisan::call('backup:run', ['--disable-notifications' => true]) === 0;

        if ($success) {
            Artisan::call('backup:clean', ['--disable-notifications' => true]);

            $this->audit->record('backup.completed', null);

            Hook::action('baobab.backup.completed');

            return true;
        }

        $output = $this->sanitizeOutput(Artisan::output());

        $this->audit->record('backup.failed', null, ['output' => $output]);

        $this->logger->error('Backup failed.', ['slug' => 'backup', 'output' => $output]);

        $this->notifier->send('core.backup.failed', User::permission('baobab.system.backups.create')->get());

        Hook::action('baobab.backup.failed', ['output' => $output]);

        return false;
    }

    /**
     * `Artisan::output()` relaie tel quel ce que `mysqldump`/`sqlite3` a
     * écrit — sur Windows notamment, la locale du process peut produire des
     * octets qui ne sont pas de l'UTF-8 valide, et `AuditEntry::data` est
     * castée en JSON : `json_encode` lève sinon plutôt que d'auditer
     * l'échec. Tronqué en plus, un dump avorté peut être verbeux.
     */
    private function sanitizeOutput(string $output): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $output);

        return mb_strimwidth($clean !== false ? $clean : '', 0, 2000, '…');
    }

    private function configureBackupPackage(BackupSetting $setting): void
    {
        config([
            'backup.backup.name' => config('baobab.backups.name'),
            'backup.backup.source.files.include' => [
                storage_path('app/public'),
                base_path('themes'),
            ],
            'backup.backup.source.files.exclude' => [],
            'backup.backup.source.databases' => [config('database.default')],
            'backup.backup.destination.disks' => config('baobab.backups.destinations'),
            'backup.cleanup.default_strategy.keep_all_backups_for_days' => 0,
            'backup.cleanup.default_strategy.keep_daily_backups_for_days' => $setting->retentionDaily(),
            'backup.cleanup.default_strategy.keep_weekly_backups_for_weeks' => $setting->retentionWeekly(),
            'backup.cleanup.default_strategy.keep_monthly_backups_for_months' => 0,
            'backup.cleanup.default_strategy.keep_yearly_backups_for_years' => 0,
            'backup.cleanup.default_strategy.delete_oldest_backups_when_using_more_megabytes_than' => $setting->maxTotalSizeMb() ?? PHP_INT_MAX,
            'backup.monitor_backups' => [],
        ]);
    }
}
