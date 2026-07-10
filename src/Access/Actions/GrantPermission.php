<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Users\Models\User;
use Spatie\Permission\Models\Role;

final class GrantPermission
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(User|Role $to, string $permission, string $guard = 'baobab'): void
    {
        $this->manager->grantPermission($to, $permission, $guard);
    }
}
