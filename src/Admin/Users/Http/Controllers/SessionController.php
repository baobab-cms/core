<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Auth\Actions\RevokeSession;
use Baobab\Users\Models\User;
use Illuminate\Http\RedirectResponse;

final class SessionController
{
    public function destroy(User $user, string $sessionId): RedirectResponse
    {
        app(RevokeSession::class)($user, $sessionId);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.users.show.session_revoked'),
        ]);

        return back();
    }
}
