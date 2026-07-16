<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Rendering\TemplateHierarchyResolver;
use Baobab\Support\Logger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Liste paginée des entrées publiées d'un Content Type adressable
 * (spec 03 §3-4). Même discipline que RenderContentEntry : données préparées
 * uniquement, annulation par le filtre `baobab.render.data` traitée comme un
 * 404.
 */
final class RenderContentArchive
{
    public function __construct(
        private readonly TemplateHierarchyResolver $hierarchy,
        private readonly RenderNotFound $notFound,
        private readonly Logger $logger,
    ) {}

    public function __invoke(ContentType $contentType): Response
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        /** @var LengthAwarePaginator<int, Model> $entries */
        $entries = $modelClass::query()
            ->where('status', 'published')
            ->paginate((int) config('baobab.rendering.per_page', 15));

        $title = $contentType->blueprint['label']['plural'] ?? $contentType->key;

        /** @var array{entries: LengthAwarePaginator<int, Model>, title: string}|null $data */
        $data = Hook::filter('baobab.render.data', ['entries' => $entries, 'title' => $title], $contentType);

        if ($data === null) {
            $this->logger->info('Rendu d\'archive annulé par un filtre baobab.render.data.', ['content_type' => $contentType->key]);

            return ($this->notFound)();
        }

        $key = Str::kebab($contentType->key);
        $view = $this->hierarchy->resolve(["archive-{$key}", 'archive', 'index']);
        $html = (string) Hook::filter('baobab.content.render', view($view, $data)->render(), $contentType);

        return response($html);
    }
}
