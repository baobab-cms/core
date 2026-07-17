<?php

declare(strict_types=1);

namespace Baobab\Seo\Actions;

use Baobab\Seo\Models\Redirect;
use Illuminate\Http\UploadedFile;

/**
 * Import CSV de redirections (spec 07 §4 : « migrez sans perdre votre
 * référencement ») — même format que `ExportRedirectsCsv` (source, target,
 * status_code, is_active), `fgetcsv` direct. Upsert par `source` : une
 * ligne déjà connue est mise à jour plutôt que dupliquée (la colonne est
 * unique en base). Ligne malformée (source/target manquants, code hors
 * 301/302/410) ignorée plutôt que d'interrompre tout l'import.
 */
final class ImportRedirectsCsv
{
    public function __construct(
        private readonly CreateRedirect $create,
        private readonly UpdateRedirect $update,
    ) {}

    /**
     * @return array{imported: int, skipped: int}
     */
    public function __invoke(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'r');

        if ($stream === false) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $rawHeader = fgetcsv($stream);
        $header = $rawHeader !== false ? array_map(strval(...), $rawHeader) : [];

        while (($row = fgetcsv($stream)) !== false) {
            /** @var array<string, string> $line */
            $line = $header !== [] ? array_combine($header, $row) : [];

            $source = trim((string) ($line['source'] ?? ''));
            $target = trim((string) ($line['target'] ?? ''));
            $statusCode = (int) ($line['status_code'] ?? 301);

            if ($source === '' || $target === '' || ! in_array($statusCode, [301, 302, 410], true)) {
                $skipped++;

                continue;
            }

            $isActive = ($line['is_active'] ?? '1') !== '0';

            $existing = Redirect::where('source', $source)->first();

            $data = ['source' => $source, 'target' => $target, 'status_code' => $statusCode, 'is_active' => $isActive, 'source_kind' => 'manual'];

            if ($existing !== null) {
                ($this->update)($existing, $data);
            } else {
                ($this->create)($data);
            }

            $imported++;
        }

        fclose($stream);

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
