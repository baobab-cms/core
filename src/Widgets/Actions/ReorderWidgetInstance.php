<?php

declare(strict_types=1);

namespace Baobab\Widgets\Actions;

use Baobab\Widgets\Models\WidgetInstance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Monte/descend une instance parmi ses voisines de la même zone (spec 10
 * §3.3 — remplace le drag & drop, patron boutons monter/descendre déjà
 * établi par la galerie/les menus). Échange l'`order` avec le voisin
 * immédiat plutôt qu'une renumérotation complète de la zone.
 */
final class ReorderWidgetInstance
{
    public function __invoke(WidgetInstance $instance, string $direction): void
    {
        $sibling = WidgetInstance::where('zone_key', $instance->zone_key)
            ->where('is_active', $instance->is_active)
            ->where('id', '!=', $instance->id)
            ->when(
                $direction === 'up',
                fn ($query) => $query->where('order', '<=', $instance->order)->orderByDesc('order'),
                fn ($query) => $query->where('order', '>=', $instance->order)->orderBy('order'),
            )
            ->first();

        if (! $sibling instanceof WidgetInstance) {
            return;
        }

        DB::transaction(function () use ($instance, $sibling): void {
            $instanceOrder = $instance->order;
            $instance->update(['order' => $sibling->order]);
            $sibling->update(['order' => $instanceOrder]);
        });

        Cache::forget("baobab.widget.{$instance->id}");
        Cache::forget("baobab.widget.{$sibling->id}");
    }
}
