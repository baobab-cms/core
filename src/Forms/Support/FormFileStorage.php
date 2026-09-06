<?php

declare(strict_types=1);

namespace Baobab\Forms\Support;

use Baobab\Forms\Models\FormSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Écrit une pièce jointe de soumission sur le disque privé des formulaires
 * (spec 14 §5) et en efface une à la suppression/purge (spec 14 §5, « aucun
 * orphelin »). Patron `UploadMedia` pour le nom de chemin
 * (`{domaine}/{Y}/{m}/{uuid}.{ext}`), sans son mécanisme de dédoublonnage par
 * checksum : une pièce jointe de soumission n'a pas vocation à être
 * réutilisée entre soumissions comme un média l'est entre entrées.
 *
 * Le fichier stocké n'a **aucun modèle Eloquent** — contrairement à `Media`,
 * il n'existe nulle part ailleurs que dans `form_submissions.payload` (spec
 * 14 §5 : « aucun orphelin, pas de quota global en v1 ») ; sa durée de vie
 * est entièrement celle de la soumission qui le référence.
 */
final class FormFileStorage
{
    /**
     * @return array{original_name: string, stored_path: string, mime_type: string, size: int}
     */
    public function store(UploadedFile $file): array
    {
        $disk = $this->disk();
        $mimeType = (string) $file->getMimeType();
        $extension = $file->extension() ?: $file->getClientOriginalExtension();
        $path = sprintf('form-submissions/%s/%s.%s', now()->format('Y/m'), (string) Str::uuid(), $extension);

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        return [
            'original_name' => (string) $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $mimeType,
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * @param  array{stored_path?: string}  $reference
     */
    public function delete(array $reference): void
    {
        $path = $reference['stored_path'] ?? null;

        if ($path === null) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    /**
     * Efface toutes les pièces jointes d'une soumission (`DeleteFormSubmission`,
     * `FormSubmissionsPurgeCommand`) — un seul endroit qui sait lire
     * `blueprint_snapshot` pour trouver les champs `file`, plutôt que dupliqué
     * dans les deux appelants.
     */
    public function deleteForSubmission(FormSubmission $submission): void
    {
        foreach ((array) ($submission->blueprint_snapshot ?? []) as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }

            $value = $submission->payload[$field['key']] ?? null;

            if (is_array($value)) {
                $this->delete($value);
            }
        }
    }

    private function disk(): string
    {
        return (string) config('baobab.forms.disk', 'local');
    }
}
