<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Audit\AuditLogger;
use Baobab\Audit\Models\AuditEntry;
use Illuminate\Console\Command;

/**
 * Purge programmée du journal d'audit (spec 12 §8.2, §11) — supprime
 * définitivement toute entrée antérieure à `baobab.audit.retention_days`
 * (défaut 365). Patron audité `FormSubmissionsPurgeCommand` : la purge est
 * elle-même une entrée du journal qu'elle vide (`audit.purged`), écrite
 * après coup — elle ne peut donc jamais s'auto-purger dans la même passe.
 */
final class AuditPurgeCommand extends Command
{
    protected $signature = 'baobab:audit:purge';

    protected $description = "Purge définitivement les entrées du journal d'audit antérieures à la rétention configurée.";

    public function __construct(private readonly AuditLogger $audit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $retentionDays = (int) config('baobab.audit.retention_days', 365);

        $count = AuditEntry::where('created_at', '<=', now()->subDays($retentionDays))->delete();

        if ($count > 0) {
            $this->audit->record('audit.purged', null, ['count' => $count, 'retention_days' => $retentionDays]);
        }

        $this->info("{$count} entrée(s) du journal d'audit purgée(s).");

        return self::SUCCESS;
    }
}
