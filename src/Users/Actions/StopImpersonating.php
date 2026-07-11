<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Facades\Hook;
use Baobab\Users\Exceptions\ImpersonationException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final class StopImpersonating
{
    public function __invoke(string $reason = 'manual'): void
    {
        $impersonatorId = Session::get('baobab.impersonator_id');

        if ($impersonatorId === null) {
            throw new ImpersonationException('No impersonation session to stop.');
        }

        /** @var User $actor */
        $actor = User::query()->findOrFail($impersonatorId);

        /** @var User|null $target */
        $target = Auth::guard('baobab')->user();

        Auth::guard('baobab')->login($actor);
        Session::forget(['baobab.impersonator_id', 'baobab.impersonation_expires_at']);
        Session::regenerate();

        Hook::action('baobab.user.impersonation.ended', $actor, $target, $reason);
    }
}
