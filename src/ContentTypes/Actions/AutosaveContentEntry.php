<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Editorial\Models\Revision;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Autosave périodique pendant l'édition (spec 09 §3, toutes les
 * `baobab.content.autosave_seconds`) — une seule révision par utilisateur et
 * par contenu, écrasée à chaque cycle (`updateOrCreate`). Aucune validation
 * stricte (contenu potentiellement incomplet en cours de frappe) et pas
 * d'entrée d'audit/hook : c'est un filet de sécurité en arrière-plan, pas un
 * geste éditorial conscient.
 */
final class AutosaveContentEntry
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(ContentType $contentType, Model $entry, array $data, User $actor): Revision
    {
        $snapshot = collect([...$entry->attributesToArray(), ...$data])
            ->except($contentType->revisionsExcept())
            ->all();

        return Revision::updateOrCreate(
            [
                'revisionable_type' => $entry->getMorphClass(),
                'revisionable_id' => $entry->getKey(),
                'author_id' => $actor->getKey(),
                'type' => 'autosave',
            ],
            ['snapshot' => $snapshot],
        );
    }
}
