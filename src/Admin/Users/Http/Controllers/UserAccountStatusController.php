<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Admin\Users\Http\Requests\AccountStatusRequest;
use Baobab\Users\Actions\DeactivateUser;
use Baobab\Users\Actions\ReactivateUser;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Désactivation et réactivation d'un compte (spec 05 §5, décision 5 k) —
 * adaptateur mince de `DeactivateUser` et `ReactivateUser`.
 */
final class UserAccountStatusController
{
    public function deactivate(AccountStatusRequest $request, User $user, DeactivateUser $deactivate): RedirectResponse
    {
        try {
            $deactivate($this->actor(), $user, $request->validated('reason'));
        } catch (HierarchyViolationException) {
            return $this->refused($user, 'baobab::admin.users.status.forbidden');
        } catch (AdminLockoutException) {
            return $this->refused($user, 'baobab::admin.users.status.lockout');
        } catch (InvalidAccountStateException) {
            return $this->refused($user, 'baobab::admin.users.status.not_active');
        }

        return $this->done($user, 'baobab::admin.users.status.deactivated');
    }

    public function reactivate(AccountStatusRequest $request, User $user, ReactivateUser $reactivate): RedirectResponse
    {
        try {
            $reactivate($this->actor(), $user, $request->validated('reason'));
        } catch (HierarchyViolationException) {
            return $this->refused($user, 'baobab::admin.users.status.forbidden');
        } catch (InvalidAccountStateException) {
            return $this->refused($user, 'baobab::admin.users.status.not_deactivated');
        }

        return $this->done($user, 'baobab::admin.users.status.reactivated');
    }

    private function done(User $user, string $key): RedirectResponse
    {
        session()->flash('toast', ['type' => 'success', 'message' => __($key, ['email' => $user->email])]);

        return redirect()->route('admin.users.show', ['user' => $user]);
    }

    private function refused(User $user, string $key): RedirectResponse
    {
        session()->flash('toast', ['type' => 'error', 'message' => __($key)]);

        return redirect()->route('admin.users.show', ['user' => $user]);
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        return $actor;
    }
}
