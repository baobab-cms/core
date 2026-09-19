<?php

declare(strict_types=1);

namespace Baobab\Audit\Models;

use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $impersonator_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $data
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read User|null $impersonator
 */
final class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_log';

    /**
     * Append-only (spec 12 §8.2, spec 16 §5) : aucune écriture individuelle sur
     * une entrée existante. La pseudonymisation RGPD (`AuditLogProvider`) et la
     * purge de rétention (`baobab:audit:purge`) passent par le builder, qui ne
     * déclenche aucun événement de modèle — ce sont les deux seules exceptions.
     */
    protected static function booted(): void
    {
        $refuse = static function (): never {
            throw new LogicException(__('baobab::privacy.erasure.audit_append_only'));
        };

        self::updating($refuse);
        self::deleting($refuse);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'impersonator_id',
        'action',
        'auditable_type',
        'auditable_id',
        'data',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Acteur réel pendant une impersonation (spec 04 §9.1 : double identité).
     *
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
