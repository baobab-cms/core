<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime une sauvegarde précise (spec 12 §4.3) — gatée par
 * `baobab.system.backups.create` (décision de séance du 17 septembre
 * 2026, spec 12 §12 décision 6 : pas de permission `.delete` dédiée).
 * `basename()` sur `$filename` avant toute opération disque : défense en
 * profondeur contre un chemin détourné, même si le contrôleur valide déjà
 * le jeton d'entrée.
 */
final class DeleteBackup
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(string $disk, string $filename): void
    {
        $filename = basename($filename);
        $path = config('baobab.backups.name').'/'.$filename;

        Storage::disk($disk)->delete($path);

        $this->audit->record('backup.deleted', null, ['disk' => $disk, 'filename' => $filename]);
    }
}
