<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Editorial\Models;

use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Verrou d'édition (spec 09 §7) : une ligne par contenu verrouillé,
 * entretenue par heartbeat (`updated_at`). Supprimée à la libération ou à
 * la prise de main — état éphémère, pas un historique.
 *
 * @property int $id
 * @property string $lockable_type
 * @property int $lockable_id
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ContentLock extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'user_id',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        $expirySeconds = (int) config('baobab.content.lock_expiry_seconds', 120);

        return $this->updated_at->lt(now()->subSeconds($expirySeconds));
    }
}
