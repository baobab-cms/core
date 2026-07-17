<?php

declare(strict_types=1);

namespace Baobab\Widgets\Actions;

use Baobab\Widgets\Core\CustomHtmlWidget;
use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Support\Facades\Gate;

/**
 * Crée une instance de widget dans une zone (spec 10 §3.3). Ajoutée en fin
 * de zone (ordre = max courant + 1). Le widget « HTML personnalisé » exige
 * `baobab.widgets.unsafe_html` (spec §4 décision 3) — vérifié ici, pas au
 * rendu (le rendu doit toujours fonctionner pour une instance déjà
 * autorisée).
 */
final class CreateWidgetInstance
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __invoke(string $zoneKey, string $widgetKey, array $settings, string $visibility = 'everyone'): WidgetInstance
    {
        if ($widgetKey === CustomHtmlWidget::key()) {
            Gate::authorize('baobab.widgets.unsafe_html');
        }

        $order = ((int) WidgetInstance::where('zone_key', $zoneKey)->max('order')) + 1;

        return WidgetInstance::create([
            'zone_key' => $zoneKey,
            'widget_key' => $widgetKey,
            'settings' => $settings,
            'order' => $order,
            'visibility' => $visibility,
            'is_active' => true,
        ]);
    }
}
