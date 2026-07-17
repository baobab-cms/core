<?php

declare(strict_types=1);

namespace Baobab\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
final class Menu extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'description'];

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id')->orderBy('order');
    }

    /**
     * @return HasMany<MenuAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(MenuAssignment::class);
    }
}
