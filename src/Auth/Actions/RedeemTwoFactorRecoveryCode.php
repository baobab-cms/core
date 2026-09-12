<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Users\Models\User;

final class RedeemTwoFactorRecoveryCode
{
    public function __invoke(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(strtoupper(trim($code)), $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill([
            'two_factor_recovery_codes' => array_values($codes),
        ])->save();

        return true;
    }
}
