<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Imports\Models\ImportJob;
use Baobab\Imports\Support\ImportReport;
use Baobab\System\Actions\ImportContent;
use Baobab\System\Actions\ValidateImport;
use Illuminate\Console\Command;

/**
 * `php artisan baobab:import {archive} --strategy=...` (spec 12 §5.3,
 * cadrage Pass F2, suivi n° 328) — dry-run toujours joué en premier
 * (systématique, spec §5.3), exécution réelle synchrone ensuite (jamais via
 * la queue, patron exact `baobab:export` → `ExportContent`). `--dry-run`
 * s'arrête après le rapport, sans jamais exécuter.
 */
final class ImportRunCommand extends Command
{
    protected $signature = 'baobab:import {archive : Chemin de l\'archive .zip} {--strategy=ignore : ignore|replace|duplicate} {--dry-run : Ne joue que le dry-run}';

    protected $description = "Importe une archive d'export de contenu, dry-run systématique puis exécution.";

    public function handle(ValidateImport $validate, ImportContent $execute): int
    {
        /** @var string $archiveOption */
        $archiveOption = $this->argument('archive');
        $path = realpath($archiveOption);

        if ($path === false) {
            $this->error("Archive introuvable : {$archiveOption}");

            return self::FAILURE;
        }

        /** @var string $strategy */
        $strategy = $this->option('strategy');

        if (! in_array($strategy, ['ignore', 'replace', 'duplicate'], true)) {
            $this->error("Stratégie inconnue : {$strategy} (ignore, replace ou duplicate).");

            return self::FAILURE;
        }

        $report = $validate($path, $strategy);

        $this->printReport($report);

        if (! $report->isValid()) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run only — nothing was imported.');

            return self::SUCCESS;
        }

        $importJob = ImportJob::create([
            'status' => 'pending',
            'archive_path' => $path,
            'strategy' => $strategy,
        ]);

        if (! $execute($importJob)) {
            $this->error("Import failed: {$importJob->error_message}");

            return self::FAILURE;
        }

        $this->info('Import completed.');

        return self::SUCCESS;
    }

    private function printReport(ImportReport $report): void
    {
        $this->line("Format version: {$report->formatVersion}");

        foreach ($report->contentTypes as $summary) {
            $this->line($summary->willCreate
                ? "  {$summary->key}: will create"
                : "  {$summary->key}: created={$summary->created} updated={$summary->updated} skipped={$summary->skipped} duplicated={$summary->duplicated}");
        }

        $this->line("Media: matched={$report->mediaMatched} to_import={$report->mediaToImport}");

        foreach ($report->errors as $error) {
            $this->error("  {$error}");
        }
    }
}
