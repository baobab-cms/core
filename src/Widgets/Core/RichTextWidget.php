<?php

declare(strict_types=1);

namespace Baobab\Widgets\Core;

use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\Widget;

/**
 * « Texte riche » (spec 10 §3.2, Tiptap) — sortie nettoyée, même mécanisme
 * que le champ `richtext` des Content Types (`Mews\Purifier\Casts\CleanHtml`,
 * spec 02 §3.2) : le helper global `clean()` qu'il délègue en interne.
 */
final class RichTextWidget extends Widget
{
    public static function key(): string
    {
        return 'baobab.rich-text';
    }

    public static function label(): string
    {
        return __('baobab::admin.widgets.rich_text_label');
    }

    public function settingsSchema(): array
    {
        return [
            [
                'key' => 'content',
                'type' => 'richtext',
                'label' => __('baobab::admin.widgets.rich_text_content_label'),
                'default' => '',
            ],
        ];
    }

    public function data(WidgetInstance $instance): array
    {
        return ['html' => clean((string) ($instance->settings['content'] ?? ''))];
    }

    public function view(): string
    {
        return 'baobab::widgets.rich-text';
    }

    public function cacheTtl(): ?int
    {
        return null;
    }
}
