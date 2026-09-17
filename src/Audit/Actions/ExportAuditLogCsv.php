<?php

declare(strict_types=1);

namespace Baobab\Audit\Actions;

use Baobab\Audit\Models\AuditEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export CSV du résultat filtré (spec 12 §8.3) — `fputcsv` direct sur un flux
 * mémoire, patron `Baobab\Forms\Actions\ExportFormSubmissionsCsv`. `$query`
 * est déjà filtrée par l'appelant (mêmes filtres que la liste) ; cette
 * Action ne fait qu'écrire.
 */
final class ExportAuditLogCsv
{
    /**
     * @param  Builder<AuditEntry>  $query
     */
    public function __invoke(Builder $query): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['date', 'acteur', 'pour_le_compte_de', 'action', 'type_objet', 'id_objet', 'details', 'ip', 'user_agent']);

        $query->with(['actor', 'impersonator'])->each(function (AuditEntry $entry) use ($stream): void {
            fputcsv($stream, [
                $entry->created_at->format('Y-m-d H:i:s'),
                $entry->actor !== null ? $entry->actor->name : __('baobab::admin.audit.system_actor'),
                $entry->impersonator !== null ? $entry->impersonator->name : '',
                $entry->action,
                $entry->auditable_type !== null ? class_basename($entry->auditable_type) : '',
                (string) ($entry->auditable_id ?? ''),
                json_encode($entry->data) ?: '',
                (string) ($entry->ip_address ?? ''),
                (string) ($entry->user_agent ?? ''),
            ]);
        });

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
