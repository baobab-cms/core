<?php

declare(strict_types=1);

namespace Baobab\Users\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Inscription front ouverte ou fermée (spec 05 §8, décision 3).
 *
 * Ligne unique, `current()` seul point d'accès — patron exact
 * `ReadingSetting`. Le défaut de la colonne est `true`, ce qui rend la
 * décision de la spec vraie même sur une base où personne n'a jamais ouvert
 * l'écran de réglages.
 *
 * @property int $id
 * @property bool $open
 */
class RegistrationSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['open'];

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1], ['open' => true]);
    }

    public static function isOpen(): bool
    {
        return self::current()->open;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['open' => 'boolean'];
    }
}
