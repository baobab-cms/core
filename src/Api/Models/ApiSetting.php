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
 * @property bool $graphql_enabled
 * @property bool $graphql_introspection_enabled
 */
class ApiSetting extends Model
{
    /**
     * Défauts PHP, pas seulement la colonne DB (patron `SeoSetting`) : une
     * instance `firstOrNew()` non encore persistée (aucun admin n'a encore
     * visité l'écran) doit se comporter comme la migration le prévoit —
     * sans ça, `rest_enabled`/`rate_limit_per_minute` vaudraient `null`
     * avant le tout premier `save()`, désactivant silencieusement l'API
     * par défaut au lieu de l'activer. `graphql_introspection_enabled` n'y
     * figure pas : son défaut dépend de l'environnement (spec 08 §3.3),
     * une expression non constante — posé par `current()` à la place.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rest_enabled' => true,
        'rate_limit_per_minute' => 60,
        'graphql_enabled' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'rest_enabled',
        'rate_limit_per_minute',
        'allowed_origins',
        'graphql_enabled',
        'graphql_introspection_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rest_enabled' => 'boolean',
            'rate_limit_per_minute' => 'integer',
            'graphql_enabled' => 'boolean',
            'graphql_introspection_enabled' => 'boolean',
        ];
    }

    /**
     * Introspection activée par défaut hors production (spec 08 §3.3) tant
     * qu'aucun admin n'a encore sauvegardé l'écran — `firstOrNew()` accepte
     * un 2ᵉ tableau de valeurs additionnelles pour l'instance non trouvée,
     * mécanisme Eloquent natif plutôt qu'un défaut statique (impossible ici,
     * `app()->environment()` n'est pas une expression constante).
     */
    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1], [
            'graphql_introspection_enabled' => ! app()->environment('production'),
        ]);
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
