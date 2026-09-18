<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\Imports\Exceptions\InvalidImportArchiveException;
use Baobab\Imports\Jobs\RunContentImportJob;
use Baobab\Imports\Models\ImportJob;
use Baobab\System\Actions\ValidateImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Écran `admin/system/import` (spec 12 §5.3, cadrage Pass F2, suivi n° 328)
 * — patron `ExportController`. Deux temps distincts (§5.3, la spec l'exige
 * explicitement) : `preview()` téléverse l'archive, la dépose durablement
 * (elle doit survivre jusqu'à l'exécution réelle, bien après cette requête)
 * et lance le dry-run synchrone ; `create()` confirme, crée l'`ImportJob` et
 * dispatche l'exécution réelle sur `baobab-low`. Le chemin de l'archive et
 * la stratégie choisie voyagent en session entre les deux requêtes plutôt
 * qu'en champs cachés du formulaire — un chemin de fichier serveur n'a rien
 * à faire dans le HTML envoyé au navigateur.
 */
final class ImportController
{
    private const string SESSION_KEY = 'baobab.import.pending';

    public function index(): View
    {
        return view('baobab::admin.system.import.index', [
            'jobs' => ImportJob::query()->latest()->limit(10)->get(),
            'columns' => $this->columns(),
            'pending' => session(self::SESSION_KEY),
            'report' => session('import_report'),
        ]);
    }

    public function preview(Request $request, ValidateImport $action): RedirectResponse
    {
        $validated = $request->validate([
            'archive' => ['required', 'file', 'max:'.intdiv((int) config('baobab.imports.max_size'), 1024)],
            'strategy' => ['required', 'in:ignore,replace,duplicate'],
        ], [], ['archive' => __('baobab::admin.import.archive_field')]);

        /** @var UploadedFile $archive */
        $archive = $request->file('archive');
        $strategy = (string) $validated['strategy'];

        $disk = (string) config('baobab.imports.disk');
        $path = trim((string) config('baobab.imports.path'), '/').'/'.((string) Str::uuid()).'.zip';

        Storage::disk($disk)->put($path, (string) file_get_contents((string) $archive->getRealPath()));

        try {
            $report = $action(Storage::disk($disk)->path($path), $strategy);
        } catch (InvalidImportArchiveException $e) {
            Storage::disk($disk)->delete($path);

            session()->flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('admin.system.import.index');
        }

        if (! $report->isValid()) {
            Storage::disk($disk)->delete($path);

            session()->flash('toast', ['type' => 'error', 'message' => __('baobab::admin.import.invalid_report')]);
            session()->flash('import_report', $report->toArray());

            return redirect()->route('admin.system.import.index');
        }

        session([self::SESSION_KEY => [
            'archive_path' => Storage::disk($disk)->path($path),
            'strategy' => $strategy,
        ]]);
        session()->flash('import_report', $report->toArray());

        return redirect()->route('admin.system.import.index');
    }

    public function create(Request $request): RedirectResponse
    {
        /** @var array{archive_path: string, strategy: string}|null $pending */
        $pending = session(self::SESSION_KEY);

        abort_if($pending === null, 400, __('baobab::admin.import.nothing_pending'));

        $importJob = ImportJob::create([
            'status' => 'pending',
            'archive_path' => $pending['archive_path'],
            'strategy' => $pending['strategy'],
            'triggered_by' => $request->user()?->id,
        ]);

        RunContentImportJob::dispatch($importJob->id)->onQueue('baobab-low');

        session()->forget(self::SESSION_KEY);
        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.import.requested')]);

        return redirect()->route('admin.system.import.index');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'strategy',
                'label' => __('baobab::admin.import.column_strategy'),
            ],
            [
                'key' => 'status',
                'label' => __('baobab::admin.import.column_status'),
                'raw' => true,
                'render' => fn (ImportJob $job): string => view('baobab::admin.system.import.partials.status-badge', ['job' => $job])->render(),
            ],
            [
                'key' => 'report',
                'label' => __('baobab::admin.import.column_summary'),
                'render' => fn (ImportJob $job): string => $this->summarize($job->report),
            ],
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.import.column_date'),
                'render' => fn (ImportJob $job): string => $job->created_at?->format('Y-m-d H:i') ?? '',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     */
    private function summarize(?array $report): string
    {
        if ($report === null) {
            return '';
        }

        /** @var list<array<string, mixed>> $contentTypes */
        $contentTypes = (array) ($report['content_types'] ?? []);

        $created = array_sum(array_column($contentTypes, 'created'));
        $updated = array_sum(array_column($contentTypes, 'updated'));

        return __('baobab::admin.import.summary', ['created' => $created, 'updated' => $updated]);
    }
}
