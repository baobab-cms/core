<?php

declare(strict_types=1);

namespace Baobab\Modules\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $title
 * @property string $type
 * @property string $version
 * @property string $provider
 * @property string $source
 * @property string $path
 * @property array<string, mixed> $manifest
 * @property string $status
 * @property Carbon|null $installed_at
 * @property Carbon|null $activated_at
 */
class Module extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'type',
        'version',
        'provider',
        'source',
        'path',
        'manifest',
        'status',
        'installed_at',
        'activated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'installed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ModulePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(ModulePermission::class);
    }

    /**
     * @return HasMany<ModuleMenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(ModuleMenuItem::class);
    }

    /**
     * @return array<string, string> Nom du module requis → contrainte de version.
     */
    public function requiredModules(): array
    {
        return $this->manifest['requires']['modules'] ?? [];
    }
}
