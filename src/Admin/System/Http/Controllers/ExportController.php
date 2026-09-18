<?php

declare(strict_types=1);

namespace Baobab\Admin\System\Http\Controllers;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Exports\Jobs\RunContentExportJob;
use Baobab\Exports\Models\ExportJob;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Écran `admin/system/export` (spec 12 §5.2, cadrage Pass F1, suivi n° 326)
 * — sélection des content types, déclenchement (job asynchrone `baobab-low`,
 * §12 décision 9), état des derniers exports, téléchargement une fois
 * `completed`. Adaptateur mince, patron `BackupsController` : aucune
 * autorisation ici, tout au middleware `can:` des routes.
 */
final class ExportController
{
    public function index(): View
    {
        return view('baobab::admin.system.export.index', [
            'contentTypes' => ContentType::query()->orderBy('key')->get(),
            'jobs' => ExportJob::query()->latest()->limit(10)->get(),
            'columns' => $this->columns(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content_type_keys' => ['required', 'array', 'min:1'],
            'content_type_keys.*' => ['string', 'exists:content_types,key'],
        ]);

        $exportJob = ExportJob::create([
            'status' => 'pending',
            'content_type_keys' => $validated['content_type_keys'],
            'triggered_by' => $request->user()?->id,
        ]);

        RunContentExportJob::dispatch($exportJob->id)->onQueue('baobab-low');

        session()->flash('toast', ['type' => 'success', 'message' => __('baobab::admin.export.requested')]);

        return redirect()->route('admin.system.export.index');
    }

    public function download(ExportJob $exportJob): StreamedResponse
    {
        abort_unless($exportJob->isDownloadable(), 404);

        /** @var string $disk */
        $disk = $exportJob->file_disk;
        /** @var string $path */
        $path = $exportJob->file_path;

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            [
                'key' => 'content_type_keys',
                'label' => __('baobab::admin.export.column_content_types'),
                'render' => fn (ExportJob $job): string => implode(', ', $job->content_type_keys),
            ],
            [
                'key' => 'status',
                'label' => __('baobab::admin.export.column_status'),
                'raw' => true,
                'render' => fn (ExportJob $job): string => view('baobab::admin.system.export.partials.status-badge', ['job' => $job])->render(),
            ],
            [
                'key' => 'created_at',
                'label' => __('baobab::admin.export.column_date'),
                'render' => fn (ExportJob $job): string => $job->created_at?->format('Y-m-d H:i') ?? '',
            ],
            [
                'key' => 'actions',
                'label' => '',
                'raw' => true,
                'render' => fn (ExportJob $job): string => $job->isDownloadable()
                    ? view('baobab::admin.system.export.partials.download-action', ['job' => $job])->render()
                    : '',
            ],
        ];
    }
}
