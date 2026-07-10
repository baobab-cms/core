<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\TwoFactorManager;
use Baobab\Users\Models\User;

final class EnableTwoFactor
{
    public function __construct(private readonly TwoFactorManager $manager) {}

    public function __invoke(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => encrypt($this->manager->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode($this->manager->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
