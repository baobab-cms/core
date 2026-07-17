<?php

declare(strict_types=1);

namespace Baobab\Seo\Models;

use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gabarit de titre SEO par Content Type adressable (spec 07 §2.2), ex.
 * `{title} — {site_name}`. `forContentType()` est le seul point d'accès —
 * une ligne créée à la volée par type, jamais écrite au blueprint (le
 * blueprint appartient au pipeline de génération de Content Types, spec 02).
 *
 * @property int $id
 * @property int $content_type_id
 * @property string|null $title_template
 * @property bool $exclude_from_sitemap
 */
class SeoContentTypeSetting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'exclude_from_sitemap' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'content_type_id',
        'title_template',
        'exclude_from_sitemap',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exclude_from_sitemap' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ContentType, $this>
     */
    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class);
    }

    public static function forContentType(ContentType $contentType): self
    {
        return self::query()->firstOrNew(['content_type_id' => $contentType->id]);
    }
}
