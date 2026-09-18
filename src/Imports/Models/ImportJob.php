<?php

declare(strict_types=1);

namespace Baobab\Imports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Suivi de l'exécution réelle d'un import de contenu (spec 12 §5.3, cadrage
 * Pass F2, suivi n° 328). `uuid` est l'identifiant public (route model
 * binding), jamais `id` — patron exact `ExportJob`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $status
 * @property string $archive_path
 * @property string $strategy
 * @property array<string, mixed>|null $report
 * @property string|null $error_message
 * @property int|null $triggered_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class ImportJob extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'archive_path',
        'strategy',
        'report',
        'error_message',
        'triggered_by',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
