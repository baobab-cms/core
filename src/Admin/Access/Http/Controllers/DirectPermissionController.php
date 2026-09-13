<?php

declare(strict_types=1);

namespace Baobab\Admin\Access\Http\Controllers;

use Baobab\Access\Actions\GrantDirectPermission;
use Baobab\Access\Actions\RevokeDirectPermission;
use Baobab\Access\Models\DirectPermissionGrant;
use Baobab\Admin\Access\Http\Requests\GrantDirectPermissionRequest;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Spec 05 §6.3 : écran global de revue des exceptions en vigueur, et
 * grant/revoke déclenchés depuis la fiche utilisateur (Pass 3.B).
 */
final class DirectPermissionController
{
    public function index(): View
    {
        $grants = DirectPermissionGrant::query()
            ->with(['user', 'permission', 'grantedBy'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('baobab::admin.access.direct-permissions', ['grants' => $grants]);
    }

    public function store(GrantDirectPermissionRequest $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        app(GrantDirectPermission::class)(
            $user,
            $request->string('permission')->toString(),
            $request->string('justification')->toString(),
            $actor,
        );

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.users.show.direct_permission_granted'),
        ]);

        return back();
    }

    public function destroy(User $user, string $permission): RedirectResponse
    {
        app(RevokeDirectPermission::class)($user, $permission);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.users.show.direct_permission_revoked'),
        ]);

        return back();
    }
}
