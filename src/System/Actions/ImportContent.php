<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Imports\Models\ImportJob;
use Baobab\Imports\Support\ImportArchiveReader;
use Baobab\Imports\Support\ImportPipeline;
use Illuminate\Support\Str;
use Throwable;

/**
 * Exécution réelle de l'import, une fois le dry-run confirmé (spec 12 §5.3,
 * cadrage Pass F2, suivi n° 328) — toujours synchrone dans sa propre
 * exécution (patron `ExportContent`) ; c'est `RunContentImportJob` qui la met
 * sur `baobab-low` pour l'admin (§12 décision 11), le CLI l'invoque
 * directement. Réutilise `ImportPipeline` avec `commit: true` — même code
 * que le dry-run qui vient de valider l'archive, jamais réimplémenté.
 */
final class ImportContent
{
    public function __construct(
        private readonly ImportPipeline $pipeline,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(ImportJob $importJob): bool
    {
        $importJob->update(['status' => 'running', 'started_at' => now()]);

        try {
            $archive = ImportArchiveReader::open($importJob->archive_path, (int) config('baobab.imports.max_size'));

            try {
                $report = $this->pipeline->run($archive, $importJob->strategy, commit: true);
            } finally {
                $archive->close();
            }

            $importJob->update([
                'status' => 'completed',
                'report' => $report->toArray(),
                'finished_at' => now(),
            ]);

            $this->audit->record('import.completed', null, [
                'import_job_id' => $importJob->id,
                'strategy' => $importJob->strategy,
            ]);

            Hook::action('baobab.import.completed', $importJob, $report);

            return true;
        } catch (Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'finished_at' => now(),
            ]);

            $this->audit->record('import.failed', null, [
                'import_job_id' => $importJob->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
