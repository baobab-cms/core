<?php

declare(strict_types=1);

namespace Baobab\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages API (spec 08 §4.3, M7 point 2 Pass B) : ligne unique, patron
 * exact `BrandingSetting`/`ReadingSetting` — `current()` est le seul point
 * d'accès, pas le framework générique de réglages par module (§5.1).
 *
 * @property int $id
 * @property bool $rest_enabled
 * @property int $rate_limit_per_minute
 * @property string|null $allowed_origins
 */
class ApiSetting extends Model
{
    /**
     * Défauts PHP, pas seulement la colonne DB (patron `SeoSetting`) : une
     * instance `firstOrNew()` non encore persistée (aucun admin n'a encore
     * visité l'écran) doit se comporter comme la migration le prévoit —
     * sans ça, `rest_enabled`/`rate_limit_per_minute` vaudraient `null`
     * avant le tout premier `save()`, désactivant silencieusement l'API
     * par défaut au lieu de l'activer.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rest_enabled' => true,
        'rate_limit_per_minute' => 60,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rest_enabled',
        'rate_limit_per_minute',
        'allowed_origins',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rest_enabled' => 'boolean',
            'rate_limit_per_minute' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1]);
    }

    /**
     * Une origine par ligne (patron `SeoSetting::social_profiles`, consommé
     * par `ComposeJsonLd`) — défaut : liste vide, aucune origine externe
     * (spec 08 §4.3).
     *
     * @return list<string>
     */
    public function allowedOrigins(): array
    {
        return array_values(collect(explode("\n", (string) $this->allowed_origins))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->all());
    }
}
