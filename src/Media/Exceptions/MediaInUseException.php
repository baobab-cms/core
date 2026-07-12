<?php

declare(strict_types=1);

namespace Baobab\Media\Exceptions;

use Baobab\Media\Models\Media;
use Baobab\Media\Models\MediaUsage;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Levée par DeleteMedia quand un média est référencé par au moins un usage
 * et que la suppression n'est pas forcée (spec 06 §5, suppression protégée).
 * Porte la liste des usages pour que l'appelant (contrôleur) l'affiche à
 * l'utilisateur et lui laisse confirmer une suppression forcée.
 */
final class MediaInUseException extends RuntimeException
{
    /**
     * @param  Collection<int, MediaUsage>  $usages
     */
    private function __construct(string $message, public readonly Collection $usages)
    {
        parent::__construct($message);
    }

    public static function forMedia(Media $media): self
    {
        return new self(
            'Ce média est utilisé et ne peut pas être supprimé sans confirmation.',
            $media->usages()->with('usable')->get(),
        );
    }
}
