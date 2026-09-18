<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Media\Models\Media;
use Baobab\Privacy\DataDeclaration;
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
}
