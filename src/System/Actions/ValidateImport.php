<?php

declare(strict_types=1);

namespace Baobab\System\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Facades\Hook;
use Baobab\Imports\Support\ImportArchiveReader;
use Baobab\Imports\Support\ImportPipeline;
use Baobab\Imports\Support\ImportReport;

/**
 * Dry-run systématique de l'import (spec 12 §5.3, cadrage Pass F2, suivi
 * n° 328) — toujours synchrone (admin comme CLI), toujours annulé : la même
 * mécanique transactionnelle que `ImportContent`, via `ImportPipeline`, mais
 * `commit: false`. Ne crée jamais un content type manquant (DDL non
 * annulable, §12 décision 11) — se contente de valider que son blueprint
 * embarqué se parse.
 */
final class ValidateImport
{
    public function __construct(
        private readonly ImportPipeline $pipeline,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(string $archivePath, string $strategy): ImportReport
    {
        $archive = ImportArchiveReader::open($archivePath, (int) config('baobab.imports.max_size'));

        try {
            $report = $this->pipeline->run($archive, $strategy, commit: false);
        } finally {
            $archive->close();
        }

        $this->audit->record('import.validated', null, ['strategy' => $strategy, 'valid' => $report->isValid()]);

        Hook::action('baobab.import.validated', $report);

        return $report;
    }
}
