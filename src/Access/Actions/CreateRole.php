<?php

declare(strict_types=1);

namespace Baobab\Access\Actions;

use Baobab\Access\AccessManager;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\Role;

final class CreateRole
{
    public function __construct(private readonly AccessManager $manager) {}

    public function __invoke(string $name, int $level, string $guard = 'baobab'): Role
    {
        /** @var User|null $actor */
        $actor = Auth::guard('baobab')->user();
        $this->manager->assertOutranks($actor, $level);

        return $this->manager->createRole($name, $level, $guard);
    }
}
