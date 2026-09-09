<?php

declare(strict_types=1);

namespace Baobab\Branding\Models;

use Baobab\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réglages de marque (spec-admin.md §11.1) : ligne unique, consommée par la
 * topbar admin et le layout e-mail (spec 13 §3.4). `current()` est le seul
 * point d'accès — pas de framework générique de réglages (§5.1) ici.
 *
 * @property int $id
 * @property int|null $logo_media_id
 * @property int|null $favicon_media_id
 * @property string|null $primary_color
 * @property array<string, array<string, string>>|null $tokens
 * @property string|null $brand_profile
 */
class BrandingSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'logo_media_id',
        'favicon_media_id',
        'primary_color',
        'tokens',
        'brand_profile',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tokens' => 'array',
    ];

    /**
     * `id` n'est pas dans `$fillable` (par construction — ce n'est jamais une
     * valeur de formulaire légitime), donc `firstOrNew(['id' => 1])` ne le
     * fixe pas sur l'instance neuve : `fill()` l'ignore silencieusement, et
     * `save()` insère alors une ligne avec un id auto-incrémenté au lieu de
     * réoccuper la ligne 1. Une fois la ligne 1 absente, plus aucune
     * sauvegarde n'est jamais relue par `current()` — le singleton se
     * transforme en lignes orphelines qui s'accumulent en silence.
     */
    public static function current(): self
    {
        $current = self::query()->find(1);

        if ($current instanceof self) {
            return $current;
        }

        $new = new self;
        $new->id = 1;

        return $new;
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function favicon(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_media_id');
    }
}
