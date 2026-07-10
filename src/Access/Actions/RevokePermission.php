<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

final class RevokePermission
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(User|Role $from, string $permission, string $guard = 'baobab'): void
    {
        if ($from instanceof User && $permission === 'baobab.admin.access') {
            throw new AdminLockoutException('Cannot revoke baobab.admin.access from a user directly.');
        }

        $this->manager->revokePermission($from, $permission, $guard);
    }
}
