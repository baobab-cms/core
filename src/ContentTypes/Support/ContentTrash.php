<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Accès à la corbeille (colonne `deleted_at`, spec 02 §4.2) pour un modèle
 * de Content Type résolu dynamiquement. `SoftDeletes` est un trait — PHPStan
 * ne peut pas vérifier `$modelClass::onlyTrashed()`/`$entry->restore()` sur
 * un `class-string<Model>` générique (aucune intersection classe+trait dans
 * son système de types). Ces deux méthodes reproduisent exactement ce que
 * fait le trait, via les méthodes de base de Builder/Model qui restent
 * statiquement vérifiables — centralisées ici plutôt que dupliquées à
 * chaque appelant (ContentController, TrashController,
 * ContentPurgeTrashCommand, RestoreContentEntryFromTrash).
 */
final class ContentTrash
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return Builder<Model>
     */
    public static function onlyTrashed(string $modelClass): Builder
    {
        return $modelClass::query()->withoutGlobalScope(SoftDeletingScope::class)->whereNotNull('deleted_at');
    }

    public static function restore(Model $entry): void
    {
        $entry->setAttribute('deleted_at', null);
        $entry->save();
    }
}
