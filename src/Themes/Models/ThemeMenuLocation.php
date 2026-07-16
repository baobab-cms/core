<?php

declare(strict_types=1);

namespace Baobab\Themes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $label
 * @property bool $is_active
 */
class ThemeMenuLocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'label', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
