<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Models;

use Baobab\Modules\Models\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $key
 * @property string $table_name
 * @property bool $is_addressable
 * @property int|null $module_id
 * @property int $version
 * @property array<string, mixed> $blueprint
 */
class ContentType extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'table_name',
        'is_addressable',
        'module_id',
        'version',
        'blueprint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_addressable' => 'boolean',
            'blueprint' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * FQCN du modèle Eloquent généré (convention de ContentTypeModuleGenerator :
     * namespace `Modules\{Key}`, classe `Models\{Key}`).
     */
    public function modelClass(): string
    {
        return "Modules\\{$this->key}\\Models\\{$this->key}";
    }

    /**
     * Répertoire du module généré (convention de ContentTypeModuleGenerator :
     * racine `baobab.content_types.modules_path`, dossier `content-{slug}`).
     */
    public function moduleDir(): string
    {
        $dirSlug = Str::kebab(Str::plural($this->key));

        return rtrim((string) config('baobab.content_types.modules_path'), '/')."/content-{$dirSlug}";
    }
}
