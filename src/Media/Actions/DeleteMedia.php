<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\Media;

/**
 * Suppression d'un média (M4 point 2b, socle minimal). Soft delete
 * uniquement (`Media` utilise déjà `SoftDeletes`) — le fichier reste sur
 * disque, cohérent avec la rétention 30 jours avant purge (spec 06 §1.2).
 * Ne vérifie aucun usage : la suppression protégée avec suivi d'usage
 * (`media_usages`, écran corbeille, purge programmée) est le socle complet
 * de M4 point 3, non construit ici — rien ne peut encore référencer un
 * média tant que les champs médias de Content Type (M4 point 4) n'existent
 * pas.
 */
final class DeleteMedia
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Media $media): void
    {
        $this->audit->record('media.deleted', $media, ['file_name' => $media->file_name]);

        $media->delete();
    }
}
