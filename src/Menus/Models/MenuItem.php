<?php

declare(strict_types=1);

namespace Baobab\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un nœud de l'arbre par liste d'adjacence (spec 10 §2.1). `type` discrimine
 * la référence effective — `content` (`linkable`, polymorphe), `archive`
 * (`content_type_key`), `custom_link` (`url`), `section` (aucune, non
 * cliquable).
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int $order
 * @property string $type
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property string|null $content_type_key
 * @property string|null $url
 * @property string|null $label
 * @property string $target
 * @property string|null $css_class
 * @property string|null $icon
 * @property string $visibility
 * @property array<string, mixed>|null $meta
 */
final class MenuItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'menu_id',
        'parent_id',
        'order',
        'type',
        'linkable_type',
        'linkable_id',
        'content_type_key',
        'url',
        'label',
        'target',
        'css_class',
        'icon',
        'visibility',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
