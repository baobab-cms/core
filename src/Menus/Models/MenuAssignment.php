<?php

declare(strict_types=1);

namespace Baobab\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $menu_id
 * @property string $location_key
 */
final class MenuAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['menu_id', 'location_key'];

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
