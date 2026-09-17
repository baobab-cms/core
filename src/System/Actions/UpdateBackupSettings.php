<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Backups\Models\BackupSetting;
use Baobab\Facades\Hook;

/**
 * Met à jour les réglages de sauvegarde (spec 12 §4.1, §4.3, amendée le
 * 17 septembre 2026) : rétention quotidienne/hebdomadaire, taille max
 * totale, bascule de la sauvegarde planifiée. Validation faite par
 * l'appelant, patron exact `UpdateSeoSettings`.
 */
final class UpdateBackupSettings
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{retention_daily?: int|null, retention_weekly?: int|null, max_total_size_mb?: int|null, scheduled_enabled?: bool}  $data
     */
    public function __invoke(array $data): BackupSetting
    {
        $setting = BackupSetting::current();
        $setting->fill($data);
        $setting->save();

        $this->audit->record('system.backups.settings.updated', $setting, $data);

        Hook::action('baobab.backup.settings.updated', $setting);

        return $setting;
    }
}
