<?php

declare(strict_types=1);

namespace Baobab\Rendering\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de lecture (spec 03 §4) : ligne unique, gouverne le mode de la
 * page d'accueil. `current()` est le seul point d'accès — patron exact
 * `BrandingSetting`, pas de framework générique de réglages (spec-admin §5.1)
 * ici.
 *
 * @property int $id
 * @property string|null $mode
 * @property string|null $page_content_type_key
 * @property int|null $page_entry_id
 * @property string|null $posts_content_type_key
 */
class ReadingSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'mode',
        'page_content_type_key',
        'page_entry_id',
        'posts_content_type_key',
    ];

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1]);
    }
}
