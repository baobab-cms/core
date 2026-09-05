<?php

declare(strict_types=1);

namespace Baobab\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un formulaire (spec 14 §2) : blueprint versionné stocké en base — jamais en
 * fichier, contrairement au générateur de thèmes ou au Studio — patron
 * `Baobab\ContentTypes\Models\ContentType`. `settings` porte les réglages non
 * liés à un champ (§3 : suites §8, anti-spam §7, rétention §6.4) ; câblés par
 * les Pass D/E, simple sac JSON pour l'instant.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property int $version
 * @property array{fields: list<array<string, mixed>>} $blueprint
 * @property array<string, mixed> $settings
 * @property bool $store_submissions
 * @property int $retention_days
 * @property bool $retain_ip
 */
class Form extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'version',
        'blueprint',
        'settings',
        'store_submissions',
        'retention_days',
        'retain_ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blueprint' => 'array',
            'settings' => 'array',
            'store_submissions' => 'boolean',
            'retention_days' => 'integer',
            'retain_ip' => 'boolean',
        ];
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
