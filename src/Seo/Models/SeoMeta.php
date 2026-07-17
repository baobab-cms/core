<?php

declare(strict_types=1);

namespace Baobab\Seo\Models;

use Baobab\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Métadonnées SEO d'une entrée de Content Type adressable (spec 07 §2.1) —
 * polymorphique, jamais de colonne sur les tables `ct_*` (spec 07 §1).
 * `forEntry()` est le seul point d'accès, patron exact
 * `BrandingSetting::current()`/`ReadingSetting::current()`.
 *
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $robots_noindex
 * @property bool $robots_nofollow
 * @property string|null $canonical_url
 * @property string|null $og_title
 * @property string|null $og_description
 * @property int|null $og_image_media_id
 */
class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    /**
     * Le défaut `false` de la migration ne s'applique qu'à l'insertion —
     * une instance neuve non sauvegardée (`SeoMeta::forEntry()` sur une
     * entrée sans metabox saisie encore) doit déjà lire `false`, pas `null`,
     * pour que `ComposeSeoMeta` compose des booléens fiables sans vérifier
     * si la ligne existe.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'robots_noindex' => false,
        'robots_nofollow' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meta_title',
        'meta_description',
        'robots_noindex',
        'robots_nofollow',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image_media_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'robots_noindex' => 'boolean',
            'robots_nofollow' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_media_id');
    }

    /**
     * `firstOrNew()`'s array argument goes through mass assignment
     * (`fill()`), qui ignore silencieusement `seoable_type`/`seoable_id`
     * (absents de `$fillable` par choix — jamais assignables depuis une
     * requête, seulement depuis ce point d'entrée) : recherche d'abord,
     * assignation explicite du morph sur l'instance neuve ensuite.
     */
    public static function forEntry(Model $entry): self
    {
        $existing = self::query()
            ->where('seoable_type', $entry->getMorphClass())
            ->where('seoable_id', $entry->getKey())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $meta = new self;
        $meta->seoable_type = $entry->getMorphClass();
        $meta->seoable_id = $entry->getKey();

        return $meta;
    }
}
