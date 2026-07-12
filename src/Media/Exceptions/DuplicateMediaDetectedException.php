<?php

declare(strict_types=1);

namespace Baobab\Media\Exceptions;

use Baobab\Media\Models\Media;
use RuntimeException;

/**
 * Levée par UploadMedia quand un fichier de checksum identique existe déjà et
 * que le comportement configuré (spec 06 §2) est « demander ». Porte le média
 * existant pour que l'appelant (contrôleur) construise une réponse 409 avec
 * de quoi laisser l'utilisateur choisir réutiliser/uploader quand même.
 */
final class DuplicateMediaDetectedException extends RuntimeException
{
    private function __construct(string $message, public readonly Media $existing)
    {
        parent::__construct($message);
    }

    public static function forExisting(Media $existing): self
    {
        return new self("Un média identique existe déjà (« {$existing->file_name} »).", $existing);
    }
}
