<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Exceptions\FolderNotEmptyException;
use Baobab\Media\Models\MediaFolder;

/**
 * Supprime un dossier virtuel (spec 06 §2). Les sous-dossiers bloquent la
 * suppression (à vider/déplacer d'abord) ; les médias qu'il contient sont
 * orphelinés vers la racine par la contrainte FK `nullOnDelete` — un dossier
 * est une étiquette d'organisation, pas un conteneur possessif.
 */
final class DeleteMediaFolder
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(MediaFolder $folder): void
    {
        if ($folder->children()->exists()) {
            throw FolderNotEmptyException::hasSubfolders($folder->name);
        }

        $this->audit->record('media_folder.deleted', $folder, ['name' => $folder->name]);

        $folder->delete();
    }
}
