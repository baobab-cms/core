<?php

declare(strict_types=1);

namespace Baobab\Seo\Models;

use Baobab\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réglages SEO globaux (spec 07 §2.2) : ligne unique, patron exact
 * `BrandingSetting`. `site_name` vide retombe sur `config('app.name')` au
 * moment de la composition (`ComposeSeoMeta`), jamais ici.
 *
 * @property int $id
 * @property string|null $site_name
 * @property string $title_separator
 * @property int|null $default_share_media_id
 * @property string|null $default_meta_description
 * @property string|null $robots_txt
 * @property bool $force_index_on_staging
 * @property string $organization_type
 * @property string|null $social_profiles
 */
class SeoSetting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'force_index_on_staging' => false,
        'organization_type' => 'Organization',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site_name',
        'title_separator',
        'default_share_media_id',
        'default_meta_description',
        'robots_txt',
        'force_index_on_staging',
        'organization_type',
        'social_profiles',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'force_index_on_staging' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1]);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function defaultShareMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'default_share_media_id');
    }
}
