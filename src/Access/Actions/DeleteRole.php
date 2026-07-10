<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\ProtectedRoleException;
use Spatie\Permission\Models\Role;

final class DeleteRole
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(Role $role): void
    {
        if ($role->name === 'super-admin') {
            throw new ProtectedRoleException('The super-admin role cannot be deleted.');
        }

        $this->manager->deleteRole($role);
    }
}
