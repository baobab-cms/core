<?php

declare(strict_types=1);

namespace Baobab\Mail\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de transport e-mail (spec 13 §2.1, suivi n° 187) : ligne unique,
 * patron exact `ApiSetting`/`BrandingSetting` — `current()` est le seul
 * point d'accès.
 *
 * @property int $id
 * @property string $mailer
 * @property string|null $from_address
 * @property string|null $from_name
 * @property array<string, mixed> $credentials
 */
class MailSetting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'mailer' => 'smtp',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mailer',
        'from_address',
        'from_name',
        'credentials',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'array',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1]);
    }
}
