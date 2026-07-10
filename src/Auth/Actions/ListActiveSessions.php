<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListActiveSessions
{
    /** @return Collection<int, \stdClass> */
    public function __invoke(User $user): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get();
    }
}
