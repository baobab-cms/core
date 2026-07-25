<?php

declare(strict_types=1);

namespace Baobab\Branding\Models;

use Baobab\Modules\Models\Module;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registre de polices (spec 18 §5.1) : une police n'est pas un média (§1
 * point 4), registre dédié avec sa propre suppression protégée
 * (`Baobab\Branding\Actions\DeleteFont`). `source` distingue `bundled`
 * (vendored Core), `theme` (embarquée par le thème actif, `theme_module_id`
 * renseigné) et `uploaded` (admin, `created_by` renseigné).
 *
 * @property int $id
 * @property string $family
 * @property string $slug
 * @property string $source
 * @property bool $is_variable
 * @property array<string, string>|null $axes
 * @property array<string, string> $files
 * @property string|null $license
 * @property string|null $license_file
 * @property bool $license_attested
 * @property int|null $theme_module_id
 * @property int|null $created_by
 */
class Font extends Model
{
    public const SOURCE_BUNDLED = 'bundled';

    public const SOURCE_THEME = 'theme';

    public const SOURCE_UPLOADED = 'uploaded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'family',
        'slug',
        'source',
        'is_variable',
        'axes',
        'files',
        'license',
        'license_file',
        'license_attested',
        'theme_module_id',
        'created_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_variable' => 'boolean',
        'axes' => 'array',
        'files' => 'array',
        'license_attested' => 'boolean',
    ];

    /**
     * @return BelongsTo<Module, $this>
     */
    public function themeModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'theme_module_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
