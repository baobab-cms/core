<?php

declare(strict_types=1);

namespace Baobab\Widgets\Actions;

use Baobab\Facades\Hook;
use Baobab\Widgets\Models\WidgetInstance;
use Baobab\Widgets\WidgetRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Résout le contenu rendu d'une zone de widgets (spec 10 §3.4). Chaque
 * instance : visibilité (live, même interprétation que les menus) → cache
 * (par instance, TTL du widget, `null` = pas de cache) → `data()` → payload
 * prêt pour la vue. Une erreur dans un widget n'est jamais fatale pour la
 * page (spec §3.4) : widget omis, loggé, message affiché seulement en
 * local.
 */
final class ResolveWidgetZone
{
    public function __construct(private readonly WidgetRegistry $widgets) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(string $zoneKey): array
    {
        $instances = WidgetInstance::where('zone_key', $zoneKey)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $items = [];

        foreach ($instances as $instance) {
            if (! $this->isVisible($instance->visibility)) {
                continue;
            }

            $item = $this->render($instance);

            if ($item !== null) {
                $items[] = $item;
                Hook::action('baobab.widget.rendered', $instance, $item['data']);
            }
        }

        /** @var list<array<string, mixed>> $filtered */
        $filtered = Hook::filter('baobab.widgets.zone', $items, $zoneKey);

        return $filtered;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function render(WidgetInstance $instance): ?array
    {
        if (! $this->widgets->has($instance->widget_key)) {
            return null;
        }

        $widget = $this->widgets->resolve($instance->widget_key);

        try {
            $ttl = $widget->cacheTtl();

            $data = $ttl !== null
                ? Cache::remember("baobab.widget.{$instance->id}", $ttl, fn (): array => $widget->data($instance))
                : $widget->data($instance);

            $view = $widget->view();
        } catch (Throwable $exception) {
            Log::error('Widget render failed', ['widget_key' => $instance->widget_key, 'instance_id' => $instance->id, 'exception' => $exception]);

            if (! app()->environment('local')) {
                return null;
            }

            $data = ['message' => $exception->getMessage()];
            $view = 'baobab::widgets.error';
        }

        return [
            'instance_id' => $instance->id,
            'widget_key' => $instance->widget_key,
            'view' => $view,
            'data' => $data,
        ];
    }

    private function isVisible(string $visibility): bool
    {
        return match (true) {
            $visibility === 'everyone' => true,
            $visibility === 'guests' => ! auth('baobab')->check(),
            $visibility === 'authenticated' => auth('baobab')->check(),
            default => (bool) auth('baobab')->user()?->can($visibility),
        };
    }
}
