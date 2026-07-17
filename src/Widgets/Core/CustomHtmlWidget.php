<?php

declare(strict_types=1);

namespace Baobab\Widgets\Core;

use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\Widget;

/**
 * « HTML personnalisé » (spec 10 §3.2, §4 décision 3) — sortie **non
 * nettoyée**, intentionnellement : c'est le contrat du widget, le risque
 * est nommé et gouverné par la permission dédiée `baobab.widgets.unsafe_html`
 * (Admin+), vérifiée à la création/modification d'une instance
 * (`CreateWidgetInstance`/`UpdateWidgetInstance`), pas ici — le rendu doit
 * toujours fonctionner pour une instance déjà autorisée.
 */
final class CustomHtmlWidget extends Widget
{
    public static function key(): string
    {
        return 'baobab.custom-html';
    }

    public static function label(): string
    {
        return __('baobab::admin.widgets.custom_html_label');
    }

    public function settingsSchema(): array
    {
        return [
            [
                'key' => 'html',
                'type' => 'textarea',
                'label' => __('baobab::admin.widgets.custom_html_content_label'),
                'default' => '',
            ],
        ];
    }

    public function data(WidgetInstance $instance): array
    {
        return ['html' => (string) ($instance->settings['html'] ?? '')];
    }

    public function view(): string
    {
        return 'baobab::widgets.custom-html';
    }

    public function cacheTtl(): ?int
    {
        return null;
    }
}
