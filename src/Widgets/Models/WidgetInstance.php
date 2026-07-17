<?php

declare(strict_types=1);

namespace Baobab\Widgets\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une instance configurée d'un widget, assignée à une zone de thème
 * (spec 10 §3.3). `widget_key` référence `WidgetRegistry`, pas une table —
 * le catalogue des widgets disponibles vit en code. `is_active = false`
 * matérialise la zone virtuelle « Inactifs » de la spec sans perdre
 * `zone_key`.
 *
 * @property int $id
 * @property string $zone_key
 * @property string $widget_key
 * @property array<string, mixed>|null $settings
 * @property int $order
 * @property string $visibility
 * @property bool $is_active
 */
final class WidgetInstance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'zone_key',
        'widget_key',
        'settings',
        'order',
        'visibility',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
