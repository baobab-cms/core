<?php

declare(strict_types=1);

namespace Baobab\Widgets\Actions;

use Baobab\Widgets\Core\CustomHtmlWidget;
use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * Modifie les réglages, la zone, la visibilité et l'état actif/inactif
 * d'une instance existante (spec 10 §3.3 — la zone virtuelle « Inactifs »
 * est `is_active = false`, `zone_key` conservé). Invalide son cache après
 * écriture (patron `SaveMenuItems`).
 */
final class UpdateWidgetInstance
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __invoke(WidgetInstance $instance, array $settings, string $zoneKey, string $visibility, bool $isActive): WidgetInstance
    {
        if ($instance->widget_key === CustomHtmlWidget::key()) {
            Gate::authorize('baobab.widgets.unsafe_html');
        }

        $instance->update([
            'settings' => $settings,
            'zone_key' => $zoneKey,
            'visibility' => $visibility,
            'is_active' => $isActive,
        ]);

        Cache::forget("baobab.widget.{$instance->id}");

        return $instance;
    }
}
