<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Facades\Hook;
use Baobab\Rendering\Models\ReadingSetting;
use Baobab\Rendering\TemplateHierarchyResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Page d'accueil (spec 03 §4, amendement du 17 juillet 2026) : page statique
 * choisie, ou derniers contenus publiés d'un Content Type adressable — mode
 * gouverné par `ReadingSetting` (« Réglages > Lecture »), jamais par une
 * convention de nommage. Sans réglage configuré, ou si la cible choisie
 * devient invalide (type/entrée supprimés, dépubliés, filtre `baobab.render.data`
 * annulé), repli sur la hiérarchie générique `home → index` — **jamais** de
 * 404 sur `/`, contrairement au reste du pipeline de rendu (où une cible
 * manquante est un 404 légitime). `index` résout naturellement vers le
 * `templates/index.blade.php` du thème actif (`TemplateHierarchyResolver`,
 * `theme::` avant `baobab::`) — la page d'accueil « par défaut » de chaque
 * thème, pas une page de bienvenue du Core figée.
 *
 * Logique proche de RenderContentEntry/RenderContentArchive mais volontairement
 * non partagée : elles résolvent par slug/prefix d'URL, celle-ci par ID/clé
 * choisis en réglage — les forcer à un contrat commun aurait été une
 * abstraction prématurée pour deux Actions déjà courtes.
 */
final class RenderHomepage
{
    public function __construct(private readonly TemplateHierarchyResolver $hierarchy) {}

    public function __invoke(): Response
    {
        $setting = ReadingSetting::current();

        return match ($setting->mode) {
            'static_page' => $this->renderStaticPage($setting) ?? $this->renderDefault(),
            'latest_posts' => $this->renderLatestPosts($setting) ?? $this->renderDefault(),
            default => $this->renderDefault(),
        };
    }

    private function renderStaticPage(ReadingSetting $setting): ?Response
    {
        if ($setting->page_content_type_key === null || $setting->page_entry_id === null) {
            return null;
        }

        $contentType = ContentType::where('key', $setting->page_content_type_key)->first();

        if (! $contentType instanceof ContentType) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        /** @var Model|null $entry */
        $entry = $modelClass::query()->where('status', 'published')->find($setting->page_entry_id);

        if ($entry === null) {
            return null;
        }

        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;
        $title = $titleField !== null ? (string) $entry->getAttribute($titleField) : $contentType->key;

        /** @var array{entry: Model, title: string}|null $data */
        $data = Hook::filter('baobab.render.data', ['entry' => $entry, 'title' => $title], $contentType, $entry);

        if ($data === null) {
            return null;
        }

        $slug = (string) $entry->getAttribute('slug');
        $view = $this->hierarchy->resolve(['home', "page-{$slug}", 'page', 'index']);
        $html = (string) Hook::filter('baobab.content.render', view($view, $data)->render(), $contentType, $entry);

        return response($html);
    }

    private function renderLatestPosts(ReadingSetting $setting): ?Response
    {
        if ($setting->posts_content_type_key === null) {
            return null;
        }

        $contentType = ContentType::where('key', $setting->posts_content_type_key)->first();

        if (! $contentType instanceof ContentType) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        $entries = $modelClass::query()
            ->where('status', 'published')
            ->paginate((int) config('baobab.rendering.per_page', 15));

        $title = $contentType->blueprint['label']['plural'] ?? $contentType->key;

        /** @var array{entries: mixed, title: string}|null $data */
        $data = Hook::filter('baobab.render.data', ['entries' => $entries, 'title' => $title], $contentType);

        if ($data === null) {
            return null;
        }

        $key = Str::kebab($contentType->key);
        $view = $this->hierarchy->resolve(['home', "archive-{$key}", 'archive', 'index']);
        $html = (string) Hook::filter('baobab.content.render', view($view, $data)->render(), $contentType);

        return response($html);
    }

    private function renderDefault(): Response
    {
        // Pas de filtres baobab.render.data/baobab.content.render ici : pas
        // de Content Type/entrée concret pour ce cas générique (patron
        // RenderNotFound, qui ne les applique pas non plus). response()->view()
        // (pas render() manuel) conserve la vue d'origine sur la réponse —
        // Illuminate\Http\Response::setContent() ne la perd que si on lui
        // passe déjà une chaîne, utile pour assertViewIs() en test.
        $view = $this->hierarchy->resolve(['home', 'index']);

        return response()->view($view);
    }
}
