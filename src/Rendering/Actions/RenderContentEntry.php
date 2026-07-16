<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Rendering\TemplateHierarchyResolver;
use Baobab\Support\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Détail d'une entrée d'un Content Type adressable (spec 03 §3-4). Données
 * préparées uniquement — jamais de logique dans la vue résolue (principe de
 * découplage, spec 03 §1). Un contenu absent, non `published`, ou dont le
 * filtre `baobab.render.data` annule (`null`) rend le template 404 comme
 * s'il n'existait pas — pas de distinction visible pour le visiteur.
 */
final class RenderContentEntry
{
    public function __construct(
        private readonly TemplateHierarchyResolver $hierarchy,
        private readonly RenderNotFound $notFound,
        private readonly Logger $logger,
    ) {}

    public function __invoke(ContentType $contentType, string $slug): Response
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        /** @var Model|null $entry */
        $entry = $modelClass::query()->where('status', 'published')->where('slug', $slug)->first();

        if ($entry === null) {
            return ($this->notFound)();
        }

        $title = $this->title($contentType, $entry);

        /** @var array{entry: Model, title: string}|null $data */
        $data = Hook::filter('baobab.render.data', ['entry' => $entry, 'title' => $title], $contentType, $entry);

        if ($data === null) {
            $this->logger->info('Rendu annulé par un filtre baobab.render.data.', ['content_type' => $contentType->key, 'slug' => $slug]);

            return ($this->notFound)();
        }

        $view = $this->hierarchy->resolve($this->candidates($contentType, $slug));
        $html = (string) Hook::filter('baobab.content.render', view($view, $data)->render(), $contentType, $entry);

        return response($html);
    }

    private function title(ContentType $contentType, Model $entry): string
    {
        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;

        return $titleField !== null ? (string) $entry->getAttribute($titleField) : $contentType->key;
    }

    /**
     * @return list<string>
     */
    private function candidates(ContentType $contentType, string $slug): array
    {
        if (strtolower($contentType->key) === 'page') {
            return ["page-{$slug}", 'page', 'index'];
        }

        $key = Str::kebab($contentType->key);

        return ["single-{$key}-{$slug}", "single-{$key}", 'single', 'index'];
    }
}
