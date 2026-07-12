<?php

declare(strict_types=1);

namespace Baobab\Media\Models;

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
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
