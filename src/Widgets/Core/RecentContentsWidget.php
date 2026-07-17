<?php

declare(strict_types=1);

namespace Baobab\Widgets\Core;

use Baobab\ContentTypes\Models\ContentType;
use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * « Contenus récents » (spec 10 §3.2) — dernières entrées publiées d'un
 * Content Type adressable donné.
 */
final class RecentContentsWidget extends Widget
{
    public static function key(): string
    {
        return 'baobab.recent-contents';
    }

    public static function label(): string
    {
        return __('baobab::admin.widgets.recent_contents_label');
    }

    public function settingsSchema(): array
    {
        $choiceOptions = ContentType::where('is_addressable', true)
            ->get()
            ->mapWithKeys(fn (ContentType $contentType): array => [$contentType->key => $contentType->key])
            ->all();

        return [
            [
                'key' => 'content_type',
                'type' => 'select',
                'label' => __('baobab::admin.widgets.recent_contents_content_type_label'),
                'required' => true,
                'options' => ['choices' => array_keys($choiceOptions)],
                'choice_options' => $choiceOptions,
            ],
            [
                'key' => 'limit',
                'type' => 'integer',
                'label' => __('baobab::admin.widgets.recent_contents_limit_label'),
                'default' => 5,
                'options' => ['min' => 1, 'max' => 20],
            ],
            [
                'key' => 'show_dates',
                'type' => 'boolean',
                'label' => __('baobab::admin.widgets.recent_contents_show_dates_label'),
                'default' => true,
            ],
        ];
    }

    public function data(WidgetInstance $instance): array
    {
        $contentType = ContentType::where('key', $instance->settings['content_type'] ?? null)->first();

        if (! $contentType instanceof ContentType) {
            return ['items' => []];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $contentType->modelClass();

        /** @var string|null $titleField */
        $titleField = $contentType->blueprint['title_field'] ?? null;
        $limit = (int) ($instance->settings['limit'] ?? 5);

        $entries = $modelClass::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        return [
            'items' => $entries->map(fn (Model $entry): array => [
                'label' => $titleField !== null ? (string) $entry->getAttribute($titleField) : $contentType->key,
                'url' => "/{$contentType->urlPrefix()}/{$entry->getAttribute('slug')}",
                'date' => $entry->getAttribute('published_at'),
            ])->all(),
            'show_dates' => (bool) ($instance->settings['show_dates'] ?? true),
        ];
    }

    public function view(): string
    {
        return 'baobab::widgets.recent-contents';
    }

    public function cacheTtl(): int
    {
        return 300;
    }
}
