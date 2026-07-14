<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Editorial\Models;

use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Un snapshot de contenu (spec 09 §6) : `manual` (sauvegarde effective),
 * `autosave` (une seule par utilisateur/contenu, écrasée à chaque cycle),
 * `working_draft` (une seule par contenu, spec 09 §3), `pre_restore` (posée
 * juste avant d'appliquer une restauration — jamais purgée).
 *
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property string $type
 * @property array<string, mixed> $snapshot
 * @property string|null $summary
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Revision extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'type',
        'snapshot',
        'summary',
        'author_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
