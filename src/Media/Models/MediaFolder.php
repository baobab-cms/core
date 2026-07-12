<?php

declare(strict_types=1);

namespace Baobab\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier virtuel (spec 06 §2) : arborescence stockée en base, indépendante
 * des chemins disque — déplacer un média entre dossiers ne touche à aucun
 * fichier. Pas de permissions par dossier en v1 (prévu v2 par la spec).
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 */
class MediaFolder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'parent_id',
    ];

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }
}
