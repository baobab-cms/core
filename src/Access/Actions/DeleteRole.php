<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\ProtectedRoleException;
use Spatie\Permission\Contracts\Role;

final class DeleteRole
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(Role $role): void
    {
        if (in_array($role->name, ['super-admin', 'visitor'], true)) {
            throw new ProtectedRoleException("The {$role->name} role cannot be deleted.");
        }

        $this->manager->deleteRole($role);
    }
}
