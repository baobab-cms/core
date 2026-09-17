<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\Audit\AuditLogger;
use Baobab\Backups\Models\BackupSetting;
use Baobab\System\Actions\CreateBackup;
use Baobab\System\Actions\DeleteBackup;
use Baobab\System\Actions\UpdateBackupSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Écran `admin/system/backups` (spec 12 §4.3) — liste, déclenchement
 * manuel, téléchargement, suppression, réglages de rétention. Adaptateur
 * mince, patron `QueuesController` : aucune autorisation ici, tout au
 * middleware `can:` des routes. Une sauvegarde n'est jamais un modèle
 * Eloquent (juste un fichier sur un disque Flysystem) — identifiée dans
 * l'URL par un jeton opaque (disque + nom de fichier encodés), jamais un
 * chemin brut.
 */
final class BackupsController
{
    public function index(): View
    {
        return view('baobab::admin.system.backups.index', [
            'backups' => $this->listBackups(),
            'columns' => $this->columns(),
            'setting' => BackupSetting::current(),
            'destinations' => config('baobab.backups.destinations'),
        ]);
    }

    public function create(CreateBackup $action): RedirectResponse
    {
        $success = $action();

        session()->flash('toast', [
            'type' => $success ? 'success' : 'error',
            'message' => __($success ? 'baobab::admin.backups.created' : 'baobab::admin.backups.create_failed'),
        ]);

        return redirect()->route('admin.system.backups.index');
    }

    public function updateSettings(Request $request, UpdateBackupSettings $action): RedirectResponse
    {
        $validated = $request->validate([
            'retention_daily' => ['nullable', 'integer', 'min:1'],
            'retention_weekly' => ['nullable', 'integer', 'min:1'],
            'max_total_size_mb' => ['nullable', 'integer', 'min:1'],
            'scheduled_enabled' => ['nullable', 'boolean'],
        ]);

        $action([
            'retention_daily' => $validated['retention_daily'] ?? null,
            'retention_weekly' => $validated['retention_weekly'] ?? null,
            'max_total_size_mb' => $validated['max_total_size_mb'] ?? null,
            'scheduled_enabled' => (bool) ($validated['scheduled_enabled'] ?? false),
        ]);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.backups.settings_updated')]);

        return redirect()->route('admin.system.backups.index');
    }

    public function download(string $token, AuditLogger $audit): StreamedResponse
    {
        [$disk, $filename] = $this->decodeToken($token);
        $path = config('baobab.backups.name').'/'.$filename;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        $audit->record('backup.downloaded', null, ['disk' => $disk, 'filename' => $filename]);

        return Storage::disk($disk)->download($path);
    }

    public function delete(string $token, DeleteBackup $action): RedirectResponse
    {
        [$disk, $filename] = $this->decodeToken($token);

        $action($disk, $filename);

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.backups.deleted')]);

        return redirect()->route('admin.system.backups.index');
    }

    /**
     * @return list<array{disk: string, filename: string, size: int, date: Carbon, token: string}>
     */
    private function listBackups(): array
    {
        $name = config('baobab.backups.name');
        $backups = [];

        foreach (config('baobab.backups.destinations') as $disk) {
            foreach (Storage::disk($disk)->files($name) as $path) {
                if (! str_ends_with($path, '.zip')) {
                    continue;
                }

                $filename = basename($path);

                $backups[] = [
                    'disk' => $disk,
                    'filename' => $filename,
                    'size' => Storage::disk($disk)->size($path),
                    'date' => Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($path)),
                    'token' => $this->encodeToken($disk, $filename),
                ];
            }
        }

        usort($backups, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $backups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'filename',
                'label' => __('baobab::admin.backups.column_filename'),
            ],
            [
                'key' => 'date',
                'label' => __('baobab::admin.backups.column_date'),
                'render' => fn (array $backup) => $backup['date']->format('Y-m-d H:i'),
            ],
            [
                'key' => 'disk',
                'label' => __('baobab::admin.backups.column_destination'),
            ],
            [
                'key' => 'size',
                'label' => __('baobab::admin.backups.column_size'),
                'render' => fn (array $backup) => number_format($backup['size'] / 1024 / 1024, 1).' Mo',
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (array $backup) => view('baobab::admin.system.backups.partials.backup-actions', ['backup' => $backup])->render(),
            ],
        ];
    }

    private function encodeToken(string $disk, string $filename): string
    {
        return Str::replace(['+', '/', '='], ['-', '_', ''], base64_encode("{$disk}::{$filename}"));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeToken(string $token): array
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        abort_if($decoded === false || ! str_contains($decoded, '::'), 400);

        [$disk, $filename] = explode('::', $decoded, 2);
        $filename = basename($filename);

        abort_unless(in_array($disk, config('baobab.backups.destinations'), true), 400);

        return [$disk, $filename];
    }
}
