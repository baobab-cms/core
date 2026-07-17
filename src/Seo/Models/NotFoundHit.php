<?php

declare(strict_types=1);

namespace Baobab\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal des 404 publics (spec 07 §4) — une ligne par chemin, `hits`
 * incrémenté à chaque nouvelle occurrence. `recordHit()` est le seul point
 * d'écriture, appelé depuis `RenderNotFound` (point de passage unique de
 * tous les 404 publics).
 *
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property string|null $referer
 * @property Carbon $last_hit_at
 */
class NotFoundHit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'hits',
        'referer',
        'last_hit_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public static function recordHit(string $path, ?string $referer): void
    {
        $hit = self::query()->firstOrNew(['path' => $path]);

        $hit->hits = ((int) $hit->hits) + 1;
        $hit->referer = $referer;
        $hit->last_hit_at = now();
        $hit->save();
    }
}
