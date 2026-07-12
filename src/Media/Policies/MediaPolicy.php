<?php

declare(strict_types=1);

namespace Baobab\Media\Policies;

use Baobab\Media\Models\Media;
use Baobab\Users\Models\User;

/**
 * Own/any (spec 05 §3.2), même patron que les policies de Content Type
 * générées (M3 point 5) : les variantes `_any` autorisent sur n'importe quel
 * média, les variantes sans `_any` exigent en plus `author_id === $user->id`.
 */
final class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('baobab.media.view');
    }

    public function view(User $user): bool
    {
        return $user->can('baobab.media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('baobab.media.upload');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->can('baobab.media.update_any')
            || ($user->can('baobab.media.update') && $media->author_id === $user->id);
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can('baobab.media.delete_any')
            || ($user->can('baobab.media.delete') && $media->author_id === $user->id);
    }

    public function restore(User $user, Media $media): bool
    {
        return $this->delete($user, $media);
    }

    /**
     * Purge définitive (M4 point 3) — irréversible, réservée à `delete_any`
     * même pour le propriétaire : barre plus haute que la simple mise à la
     * corbeille.
     */
    public function forceDelete(User $user, Media $media): bool
    {
        return $user->can('baobab.media.delete_any');
    }
}
