<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\ProtectedRoleException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\Role;

final class UpdateRole
{
    public function __construct(private readonly AccessManager $manager) {}

    /** @param array<string, mixed> $data */
    public function __invoke(Role $role, array $data): Role
    {
        if ($role->name === 'super-admin') {
            unset($data['level'], $data['name']);
        }

        if (empty($data)) {
            throw new ProtectedRoleException('The super-admin role name and level cannot be changed.');
        }

        /** @var User|null $actor */
        $actor = Auth::guard('baobab')->user();
        $this->manager->assertOutranks($actor, (int) $role->getAttribute('level'));

        if (isset($data['level'])) {
            $this->manager->assertOutranks($actor, (int) $data['level']);
        }

        return $this->manager->updateRole($role, $data);
    }
}
