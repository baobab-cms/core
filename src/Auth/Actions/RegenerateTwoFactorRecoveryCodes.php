<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\TwoFactorManager;
use Baobab\Users\Models\User;
use InvalidArgumentException;

final class RegenerateTwoFactorRecoveryCodes
{
    public function __construct(private readonly TwoFactorManager $manager) {}

    /** @return list<string> */
    public function __invoke(User $user): array
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new InvalidArgumentException('Two-factor authentication has not been confirmed for this user.');
        }

        $codes = $this->manager->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return $codes;
    }
}
