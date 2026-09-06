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
        /** @var array<string, string> $types */
        $types = array_column((array) ($form->blueprint['fields'] ?? []), 'type', 'key');

        fputcsv($stream, [...$keys, 'status', 'submitted_at']);

        $query->each(function (FormSubmission $submission) use ($stream, $keys, $types): void {
            $row = array_map(
                fn (string $key): string => $this->cellValue($submission->payload[$key] ?? null, $types[$key] ?? null),
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

    /**
     * Un champ `file` (Pass C3) stocke une référence (`original_name`,
     * `stored_path`...), jamais un scalaire — le caster tel quel produirait
     * le mot littéral « Array ». Le nom d'origine seul est exporté, jamais un
     * lien signé : un export CSV n'a pas de date de péremption, contrairement
     * à `URL::temporarySignedRoute()`.
     */
    private function cellValue(mixed $value, ?string $type): string
    {
        if ($type === 'file') {
            return is_array($value) ? (string) ($value['original_name'] ?? '') : '';
        }

        return (string) ($value ?? '');
    }
}
