<?php

declare(strict_types=1);

namespace Baobab\Telemetry\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Consentement à la télémétrie (spec 15 §8 point 2).
 *
 * Ligne unique, `current()` seul point d'accès — patron exact
 * `RegistrationSetting`. Le défaut de la colonne est `false` : l'opt-in est
 * explicite, et une base muette vaut un refus.
 *
 * **Ce modèle ne transmet rien.** Il ne porte que la réponse donnée à
 * l'installation ; l'émission réseau, l'écran d'administration qui permettra
 * de la retirer et la page qui documente les données envoyées vivent dans une
 * brique séparée (suivi n° 223). Séparer les deux est délibéré : le
 * consentement doit exister et être révocable avant que quoi que ce soit ne
 * parte, jamais l'inverse.
 *
 * @property int $id
 * @property bool $enabled
 */
class TelemetrySetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['enabled'];

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1], ['enabled' => false]);
    }

    public static function isEnabled(): bool
    {
        return self::current()->enabled;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
