<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Crée ou met à jour une ligne de contenu d'un Content Type construit (M3
 * point 5). `author_id` n'est posé qu'à la création — une modification ne
 * change jamais la paternité (spec 05 §3.2, own/any repose dessus). Capture
 * une révision `manual` après chaque sauvegarde effective (spec 09 §6) —
 * fait partie de l'action elle-même, comme les hooks et l'audit.
 */
final class SaveContentEntry
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CaptureRevision $captureRevision,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(ContentType $contentType, array $data, User $actor, ?Model $entry = null): Model
    {
        $isNew = $entry === null;

        if ($entry === null) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();
            $entry = new $modelClass;
        }

        Hook::action('baobab.content.saving', $contentType, $entry, $data);

        $entry->fill($data);

        if ($isNew) {
            $entry->setAttribute('author_id', $actor->getKey());
        }

        $entry->save();

        $this->audit->record($isNew ? 'content.created' : 'content.updated', $entry, ['content_type' => $contentType->key]);

        ($this->captureRevision)($contentType, $entry, 'manual', $actor);

        Hook::action('baobab.content.saved', $contentType, $entry, $isNew, $data);

        return $entry;
    }
}
