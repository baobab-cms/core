<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\ContentStateMachine;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * `draft`/`pending` → `published`, directement (spec 09 §2.2) : « le
 * workflow n'est pas imposé à ceux qui peuvent publier ». `published_at` est
 * toujours posé à l'instant présent, y compris en republiant après un
 * `unpublish` — sémantique « publier maintenant » sans ambiguïté.
 */
final class PublishContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ContentStateMachine $machine,
    ) {}

    public function __invoke(ContentType $contentType, Model $entry, ?User $actor = null): Model
    {
        $from = (string) $entry->getAttribute('status');
        $this->machine->assertAllowed($contentType, $from, 'publish');

        $entry->update(['status' => 'published', 'published_at' => now()]);

        $this->audit->record('content.published', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.published', $contentType, $entry);
        Hook::action('baobab.content.transitioned', $contentType, $entry, $from, 'published', $actor);

        return $entry;
    }
}
