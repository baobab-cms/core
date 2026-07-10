<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\TwoFactorManager;
use Baobab\Users\Models\User;
use InvalidArgumentException;

final class ConfirmTwoFactorCode
{
    public function __construct(private readonly TwoFactorManager $manager) {}

    public function __invoke(User $user, string $code): void
    {
        if ($user->two_factor_secret === null) {
            throw new InvalidArgumentException('Two-factor authentication has not been enabled for this user.');
        }

        if (! $this->manager->verify($user, $code)) {
            throw new InvalidArgumentException('The provided two-factor authentication code is invalid.');
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
