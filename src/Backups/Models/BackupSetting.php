<?php

declare(strict_types=1);

namespace Baobab\Backups\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de sauvegarde (spec 12 §4.1) : ligne unique, patron exact
 * `SeoSetting`/`BrandingSetting`. Une colonne de rétention `null` retombe
 * sur `config('baobab.backups.retention')` au moment de la composition
 * (`CreateBackup`), jamais ici — même règle que `SeoSetting::site_name`.
 *
 * @property int $id
 * @property int|null $retention_daily
 * @property int|null $retention_weekly
 * @property int|null $max_total_size_mb
 * @property bool $scheduled_enabled
 */
class BackupSetting extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scheduled_enabled' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'retention_daily',
        'retention_weekly',
        'max_total_size_mb',
        'scheduled_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrNew(['id' => 1]);
    }

    public function retentionDaily(): int
    {
        return $this->retention_daily ?? (int) config('baobab.backups.retention.daily', 7);
    }

    public function retentionWeekly(): int
    {
        return $this->retention_weekly ?? (int) config('baobab.backups.retention.weekly', 4);
    }

    public function maxTotalSizeMb(): ?int
    {
        return $this->max_total_size_mb ?? config('baobab.backups.retention.max_total_mb');
    }
}
