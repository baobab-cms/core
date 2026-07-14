<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\Audit\AuditLogger;
use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Modifie un contenu déjà publié sans le dépublier (spec 09 §3) : la
 * modification est stockée comme révision de travail (une seule par
 * contenu, `updateOrCreate` — pas de version historisée à chaque frappe),
 * la table `ct_*` n'est jamais touchée. `$data` est fusionné à l'état
 * courant de l'entrée pour que le snapshot soit un état complet, comparable
 * à la version publiée via RevisionDiffer.
 */
final class SaveWorkingDraftEntry
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(ContentType $contentType, Model $entry, array $data, User $actor): Revision
    {
        $snapshot = collect([...$entry->attributesToArray(), ...$data])
            ->except($contentType->revisionsExcept())
            ->all();

        $draft = Revision::updateOrCreate(
            [
                'revisionable_type' => $entry->getMorphClass(),
                'revisionable_id' => $entry->getKey(),
                'type' => 'working_draft',
            ],
            [
                'snapshot' => $snapshot,
                'author_id' => $actor->getKey(),
            ],
        );

        $this->audit->record('content.working_draft.saved', $entry, ['content_type' => $contentType->key]);

        Hook::action('baobab.content.working_draft.saved', $contentType, $entry, $draft);

        return $draft;
    }
}
