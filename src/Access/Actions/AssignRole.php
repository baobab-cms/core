<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Users\Models\User;
use Spatie\Permission\Contracts\Role;

final class AssignRole
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(User $user, Role $role): void
    {
        $this->manager->assignRole($user, $role);
    }
}
