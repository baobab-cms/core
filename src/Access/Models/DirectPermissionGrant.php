<?php

declare(strict_types=1);

namespace Baobab\Access\Models;

use Baobab\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Permission;

/**
 * @property int $id
 * @property int $user_id
 * @property int $permission_id
 * @property string $justification
 * @property int|null $granted_by
 * @property-read User $user
 * @property-read Permission $permission
 * @property-read User|null $grantedBy
 */
final class DirectPermissionGrant extends Model
{
    protected $table = 'baobab_direct_permission_grants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'permission_id',
        'justification',
        'granted_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
