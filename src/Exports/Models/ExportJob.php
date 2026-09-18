<?php

declare(strict_types=1);

namespace Baobab\Exports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Suivi d'un export de contenu (spec 12 §5.2, cadrage Pass F1, suivi n° 326).
 * `uuid` est l'identifiant public (route model binding), jamais `id`.
 *
 * @property int $id
 * @property string $uuid
 * @property string $status
 * @property list<string> $content_type_keys
 * @property string|null $file_disk
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $error_message
 * @property int|null $triggered_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class ExportJob extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'content_type_keys',
        'file_disk',
        'file_path',
        'file_size',
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
            'content_type_keys' => 'array',
            'file_size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && $this->file_path !== null;
    }

    /**
     * `HasUuids` ne remplit que la colonne `uuid` (patron exact
     * `ContentTypeProfile::publicIdentifierMethods()` pour un type
     * non-adressable) — `id` reste l'auto-incrément interne, jamais exposé
     * en route.
     *
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
