<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Spatie\Permission\Models\Role;

final class CreateRole
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(string $name, int $level, string $guard = 'baobab'): Role
    {
        return $this->manager->createRole($name, $level, $guard);
    }
}
