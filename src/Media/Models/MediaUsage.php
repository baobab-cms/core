<?php

declare(strict_types=1);

namespace Baobab\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une ligne = un média référencé par un modèle (spec 06 §5) — many-to-many
 * polymorphique matérialisé en table réelle plutôt qu'une relation Eloquent
 * `morphToMany` directe, pour porter `field_key` (quel champ a produit cette
 * référence, utile pour resynchroniser un seul champ sans toucher aux autres).
 *
 * @property int $id
 * @property int $media_id
 * @property string $usable_type
 * @property int $usable_id
 * @property string|null $field_key
 */
final class MediaUsage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'media_id',
        'usable_type',
        'usable_id',
        'field_key',
    ];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}
