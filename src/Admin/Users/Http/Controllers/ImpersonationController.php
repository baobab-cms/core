<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Users\Actions\Impersonate;
use Baobab\Users\Actions\StopImpersonating;
use Baobab\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class ImpersonationController
{
    public function store(User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        app(Impersonate::class)($actor, $user);

        return redirect()->route('admin.dashboard');
    }

    public function destroy(): RedirectResponse
    {
        app(StopImpersonating::class)();

        return redirect()->route('admin.dashboard');
    }
}
