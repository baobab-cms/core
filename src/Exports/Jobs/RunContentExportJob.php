<?php

declare(strict_types=1);

namespace Baobab\Exports\Jobs;

use Baobab\Exports\Models\ExportJob;
use Baobab\System\Actions\ExportContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fin wrapper de queue pour le déclenchement admin (spec 12 §5.2, §12
 * décision 9) — l'exécution réelle est `ExportContent`, toujours
 * synchrone dans son propre code ; seul ce job la met sur `baobab-low`.
 * Le CLI (`baobab:export`) invoque `ExportContent` directement, sans passer
 * par ce job, patron `CreateBackup`.
 */
final class RunContentExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $exportJobId) {}

    public function handle(ExportContent $action): void
    {
        $exportJob = ExportJob::find($this->exportJobId);

        if ($exportJob === null) {
            return;
        }

        $action($exportJob);
    }
}
