<?php

declare(strict_types=1);

namespace Baobab\Media\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\Media\Models\Media;

/**
 * Métadonnées éditoriales d'un média (M4 point 2b, spec 06 §1.2) —
 * `title`/`alt`/`caption`/`description`. L'autorisation (policy `update`,
 * own/any) est vérifiée par l'appelant, cette Action ne fait que persister.
 */
final class UpdateMediaMetadata
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title?: ?string, alt?: ?string, caption?: ?string, description?: ?string}  $metadata
     */
    public function __invoke(Media $media, array $metadata): Media
    {
        $media->update([
            'title' => $metadata['title'] ?? null,
            'alt' => $metadata['alt'] ?? null,
            'caption' => $metadata['caption'] ?? null,
            'description' => $metadata['description'] ?? null,
        ]);

        $this->audit->record('media.updated', $media, ['fields' => array_keys($metadata)]);

        return $media;
    }
}
