<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Users\Models\User;

final class DisableTwoFactor
{
    public function __invoke(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
