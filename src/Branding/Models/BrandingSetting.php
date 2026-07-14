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
    ];

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1]);
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
