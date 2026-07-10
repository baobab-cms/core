<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Models\Role as RoleModel;

final class RemoveRole
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(User $user, Role $role): void
    {
        if ($role->name === 'super-admin' && $this->isLastSuperAdmin($user)) {
            throw new AdminLockoutException('Cannot remove the last super-admin.');
        }

        if ($this->isActingOnSelf($user) && $this->wouldLoseOwnAdminAccess($user, $role)) {
            throw new AdminLockoutException('Cannot remove your own last role granting admin access.');
        }

        $this->manager->removeRole($user, $role);
    }

    private function isLastSuperAdmin(User $user): bool
    {
        if (! $user->hasRole('super-admin', 'baobab')) {
            return false;
        }

        return User::role('super-admin', 'baobab')->count() <= 1;
    }

    private function isActingOnSelf(User $user): bool
    {
        $actor = Auth::guard('baobab')->user();

        return $actor instanceof User && $actor->is($user);
    }

    private function wouldLoseOwnAdminAccess(User $user, Role $role): bool
    {
        if (! $user->getAllPermissions()->contains('name', 'baobab.admin.access')) {
            return false;
        }

        if ($user->getDirectPermissions()->contains('name', 'baobab.admin.access')) {
            return false;
        }

        $stillGranted = RoleModel::whereIn('id', $user->roles->pluck('id'))
            ->where('name', '!=', $role->name)
            ->get()
            ->contains(fn (RoleModel $remaining): bool => $remaining->permissions->contains('name', 'baobab.admin.access'));

        return ! $stillGranted;
    }
}
