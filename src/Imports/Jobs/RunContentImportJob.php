<?php

declare(strict_types=1);

namespace Baobab\Imports\Jobs;

use Baobab\Imports\Models\ImportJob;
use Baobab\System\Actions\ImportContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fin wrapper de queue pour le déclenchement admin (spec 12 §5.3, §12
 * décision 11) — l'exécution réelle est `ImportContent`, toujours synchrone
 * dans son propre code ; seul ce job la met sur `baobab-low`. Le CLI
 * (`baobab:import`) invoque `ImportContent` directement, patron
 * `RunContentExportJob`.
 */
final class RunContentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $importJobId) {}

    public function handle(ImportContent $action): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if ($importJob === null) {
            return;
        }

        $action($importJob);
    }
}
