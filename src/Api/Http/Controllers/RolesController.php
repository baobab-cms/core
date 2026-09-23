<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Baobab\Api\Support\ApiActor;
use Baobab\Users\Models\User;
use Baobab\Users\UserDirectory;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * `GET /api/v1/roles` (spec 05 §7, décision 5) : lecture seule, pour qu'un
 * client sache quel rôle passer à une invitation. `assignable` dit si
 * l'appelant peut l'attribuer (niveau strictement inférieur au sien).
 */
final class RolesController
{
    public function index(): JsonResponse
    {
        $actor = app(ApiActor::class)();

        abort_unless($actor instanceof User, 401);
        abort_unless(UserDirectory::allows($actor), 403);

        $level = $actor->level();

        $roles = Role::query()
            ->where('guard_name', 'baobab')
            ->orderByDesc('level')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'level' => (int) $role->getAttribute('level'),
                'assignable' => (int) $role->getAttribute('level') < $level,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $roles]);
    }
}
