<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class RevokeSession
{
    public function __invoke(User $user, string $sessionId): void
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();
    }
}
