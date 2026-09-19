<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Media\Models\Media;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\Subject;

/** `core.media` (spec 16 §2.1) : les fichiers déposés, corbeille comprise. */
final class MediaProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.media';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.media.title'),
            nature: __('baobab::privacy.media.nature'),
            purpose: __('baobab::privacy.media.purpose'),
            legalBasis: __('baobab::privacy.media.legal_basis'),
            retention: __('baobab::privacy.media.retention', ['days' => (int) config('baobab.media.trash_retention_days', 30)]),
        );
    }

    public function locate(Subject $subject): bool
    {
        $userId = $this->userIdOf($subject);

        return $userId !== null && Media::withTrashed()->where('author_id', $userId)->exists();
    }

    /**
     * Les métadonnées de chaque média déposé (corbeille comprise) et son
     * fichier. Un média externe (`external_url`) n'a pas d'octets chez nous :
     * seule sa référence est exportée. Le nom dans l'archive est préfixé de
     * l'uuid, deux médias pouvant porter le même nom de fichier.
     */
    public function export(Subject $subject): PersonalDataExport
    {
        $items = [];
        $files = [];

        foreach (Media::withTrashed()->where('author_id', $this->userIdOf($subject))->orderBy('id')->get() as $media) {
            $archiveName = null;

            if ($media->disk !== '' && $media->path !== '') {
                $archiveName = "{$media->uuid}-{$media->file_name}";
                $files[$archiveName] = ['disk' => $media->disk, 'path' => $media->path];
            }

            $items[] = [
                'uuid' => $media->uuid,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'title' => $media->title,
                'alt' => $media->alt,
                'caption' => $media->caption,
                'description' => $media->description,
                'external_url' => $media->external_url,
                'uploaded_at' => $media->created_at?->toIso8601String(),
                'in_trash' => $media->trashed(),
                'file' => $archiveName,
            ];
        }

        return new PersonalDataExport(['media' => $items], $files);
    }
}
