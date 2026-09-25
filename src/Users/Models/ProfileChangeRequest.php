<?php

declare(strict_types=1);

namespace Baobab\Users\Models;

use Baobab\Users\ProfileChangeStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande de changement de nom ou d'e-mail en attente (spec 05 §5,
 * décision 5 f-g et j). Une seule par compte.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $requested_by
 * @property string|null $new_name
 * @property string|null $new_email
 * @property ProfileChangeStage $stage
 * @property bool $forced
 * @property string|null $justification
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property User $user
 * @property User|null $requester
 */
final class ProfileChangeRequest extends Model
{
    protected $table = 'user_profile_changes';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'requested_by',
        'new_name',
        'new_email',
        'stage',
        'forced',
        'justification',
        'token_hash',
        'expires_at',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'stage' => ProfileChangeStage::class,
            'forced' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * La demande que ce jeton confirme, si elle existe encore et n'a pas
     * expiré. Le jeton de l'étape précédente ne la retrouve plus : il change
     * à chaque étape.
     */
    public static function findValid(string $token): ?self
    {
        $request = self::query()->where('token_hash', self::hashToken($token))->first();

        return $request !== null && $request->expires_at->isFuture() ? $request : null;
    }
}
