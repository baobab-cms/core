<?php

declare(strict_types=1);

namespace Baobab\Modules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $module_id
 * @property string $key
 * @property string $label
 * @property list<string>|null $default_roles
 */
class ModulePermission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'module_id',
        'key',
        'label',
        'default_roles',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_roles' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
