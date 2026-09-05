<?php

declare(strict_types=1);

namespace Baobab\Forms\Actions;

use Baobab\Forms\Models\Form;
use Baobab\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export CSV du résultat filtré (spec 14 §6.2) — `fputcsv` direct sur un flux
 * mémoire, patron `Baobab\Seo\Actions\ExportRedirectsCsv`. `$query` est déjà
 * filtrée par l'appelant (mêmes filtres que la liste) ; cette Action ne fait
 * qu'écrire.
 *
 * Colonnes dérivées du blueprint **courant** du formulaire, pas du snapshot
 * de chaque soumission (`blueprint_snapshot`) : un tableur veut des colonnes
 * stables d'un export à l'autre, quitte à laisser une cellule vide pour une
 * soumission dont un champ a depuis été renommé — la fiche de détail, elle,
 * reste fidèle au snapshot.
 */
final class ExportFormSubmissionsCsv
{
    /**
     * @param  Builder<FormSubmission>  $query
     */
    public function __invoke(Form $form, Builder $query): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        /** @var list<string> $keys */
        $keys = array_column((array) ($form->blueprint['fields'] ?? []), 'key');

        fputcsv($stream, [...$keys, 'status', 'submitted_at']);

        $query->each(function (FormSubmission $submission) use ($stream, $keys): void {
            $row = array_map(
                fn (string $key): string => (string) ($submission->payload[$key] ?? ''),
                $keys,
            );
            $row[] = $submission->status->value;
            $row[] = $submission->created_at->format('Y-m-d H:i:s');

            fputcsv($stream, $row);
        });

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
