<?php

declare(strict_types=1);

namespace Baobab\Media\Models;

use Baobab\Media\Conversions\MediaVariantResolver;
use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $file_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $caption
 * @property string|null $description
 * @property string $checksum
 * @property int|null $folder_id
 * @property int|null $author_id
 * @property array<string, mixed> $conversions
 * @property array<string, mixed> $meta
 * @property float|null $focal_x
 * @property float|null $focal_y
 */
class Media extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
        'width',
        'height',
        'title',
        'alt',
        'caption',
        'description',
        'checksum',
        'folder_id',
        'author_id',
        'conversions',
        'meta',
        'focal_x',
        'focal_y',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'conversions' => 'array',
            'meta' => 'array',
            'focal_x' => 'float',
            'focal_y' => 'float',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * URL de la variante WebP d'un preset (spec 06 §4.1) — génère et persiste
     * à la volée si le preset n'a pas encore de variante (déclaré après
     * coup). Logique dans MediaVariantResolver, cette méthode n'est qu'un
     * point d'accès pratique depuis le modèle.
     */
    public function variantUrl(string $preset): ?string
    {
        return app(MediaVariantResolver::class)->resolve($this, $preset);
    }
}
