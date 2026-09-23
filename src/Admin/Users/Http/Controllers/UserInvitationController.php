<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Admin\Users\Http\Requests\InviteUserRequest;
use Baobab\Admin\Users\UserRoleOptions;
use Baobab\Users\Actions\CancelUserInvitation;
use Baobab\Users\Actions\InviteUser;
use Baobab\Users\Actions\SendUserInvitation;
use Baobab\Users\Exceptions\InvitationNotPendingException;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Invitation d'un utilisateur (spec 05 §5, décision 5) — adaptateur mince de
 * `InviteUser` et `SendUserInvitation`.
 */
final class UserInvitationController
{
    public function create(UserRoleOptions $roles): View
    {
        return view('baobab::admin.users.create', [
            'roles' => $roles->assignableBy($this->actor()),
        ]);
    }

    public function store(InviteUserRequest $request, InviteUser $invite): RedirectResponse
    {
        try {
            $user = $invite(
                $this->actor(),
                (string) $request->validated('name'),
                (string) $request->validated('email'),
                (string) $request->validated('role'),
            );
        } catch (HierarchyViolationException) {
            return back()->withInput()->withErrors(['role' => __('baobab::admin.users.invite.role_forbidden')]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.users.invite.sent', ['email' => $user->email]),
        ]);

        return redirect()->route('admin.users.show', ['user' => $user]);
    }

    public function resend(User $user, SendUserInvitation $send): RedirectResponse
    {
        try {
            $send($user, $this->actor());
        } catch (HierarchyViolationException) {
            abort(403);
        } catch (InvitationNotPendingException) {
            session()->flash('toast', ['type' => 'error', 'message' => __('baobab::admin.users.invite.not_pending')]);

            return redirect()->route('admin.users.show', ['user' => $user]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.users.invite.resent', ['email' => $user->email]),
        ]);

        return redirect()->route('admin.users.show', ['user' => $user]);
    }

    public function cancel(User $user, CancelUserInvitation $cancel): RedirectResponse
    {
        $email = $user->email;

        try {
            $cancel($this->actor(), $user);
        } catch (HierarchyViolationException) {
            abort(403);
        } catch (InvitationNotPendingException) {
            session()->flash('toast', ['type' => 'error', 'message' => __('baobab::admin.users.invite.not_pending')]);

            return redirect()->route('admin.users.show', ['user' => $user]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.users.invite.cancelled', ['email' => $email]),
        ]);

        return redirect()->route('admin.users.index');
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        return $actor;
    }
}
