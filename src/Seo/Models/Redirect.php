<?php

declare(strict_types=1);

namespace Baobab\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une redirection publique (spec 07 §3-4) — historique de slug
 * (`source_kind: 'auto'`, générée par `BaobabServiceProvider::registerSlugRedirectListener()`)
 * et gestionnaire manuel (`source_kind: 'manual'`, écran `admin/redirects`)
 * partagent cette même table et le même mécanisme de résolution
 * (`ResolveRedirectTarget`). `source` porte directement un `*` pour un
 * motif joker (`/ancien-blog/*`) — sa présence suffit à le distinguer d'une
 * correspondance exacte, pas de colonne booléenne séparée.
 *
 * @property int $id
 * @property string $source
 * @property string $target
 * @property int $status_code
 * @property bool $is_active
 * @property int $hit_count
 * @property Carbon|null $last_hit_at
 * @property string $source_kind
 */
class Redirect extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'target',
        'status_code',
        'is_active',
        'source_kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_active' => 'boolean',
            'hit_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    public function isPattern(): bool
    {
        return str_contains($this->source, '*');
    }
}
