<?php

declare(strict_types=1);

namespace Baobab\Modules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $module_id
 * @property int|null $parent_id
 * @property string $label
 * @property string|null $icon
 * @property string|null $route
 * @property string|null $permission
 * @property int $order
 */
class ModuleMenuItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'module_id',
        'parent_id',
        'label',
        'icon',
        'route',
        'permission',
        'order',
    ];

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
