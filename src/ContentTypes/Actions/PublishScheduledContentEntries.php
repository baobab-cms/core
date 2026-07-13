<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Bascule en `published` tout contenu `scheduled` dont l'échéance est
 * atteinte (spec 09 §4) — appelée chaque minute par
 * `content:publish-due` (Baobab\Scheduler\SchedulerRegistrar). Système, pas
 * d'acteur : réutilise PublishContentEntry (même transition, mêmes hooks/
 * audit que le bouton « Publier » manuel) pour chaque ligne due.
 */
final class PublishScheduledContentEntries
{
    public function __construct(private readonly PublishContentEntry $publish) {}

    public function __invoke(): int
    {
        $published = 0;

        foreach (ContentType::whereNotNull('module_id')->get() as $contentType) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $contentType->modelClass();

            if (! class_exists($modelClass)) {
                continue;
            }

            $due = $modelClass::query()
                ->where('status', 'scheduled')
                ->where('published_at', '<=', now())
                ->get();

            foreach ($due as $entry) {
                ($this->publish)($contentType, $entry);
                $published++;
            }
        }

        return $published;
    }
}
