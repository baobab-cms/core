<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Seo\Models\Redirect;

/**
 * Export CSV des redirections (spec 07 §4 : « indispensable pour les
 * migrations depuis WordPress ») — `fputcsv` direct sur un flux mémoire,
 * aucune dépendance (`league/csv` n'existe nulle part dans ce code base,
 * besoin trop simple pour la justifier).
 */
final class ExportRedirectsCsv
{
    public function __invoke(): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['source', 'target', 'status_code', 'is_active']);

        Redirect::query()->orderBy('source')->each(function (Redirect $redirect) use ($stream): void {
            fputcsv($stream, [
                $redirect->source,
                $redirect->target,
                (string) $redirect->status_code,
                $redirect->is_active ? '1' : '0',
            ]);
        });

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
