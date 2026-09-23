<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Users\Actions\GrantUserRole;
use Baobab\Users\Actions\RevokeUserRole;
use Baobab\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Rôles d'un utilisateur depuis sa fiche (spec 05 §5, décision 5) —
 * adaptateur mince de `GrantUserRole` / `RevokeUserRole`.
 */
final class UserRoleController
{
    public function store(Request $request, User $user, GrantUserRole $grant): RedirectResponse
    {
        $validated = $request->validate([
            // La table des rôles est nommée par la config de Spatie : on la
            // résout depuis le modèle, jamais en dur.
            'role' => ['required', 'integer', Rule::exists(Role::class, 'id')],
        ]);

        /** @var Role $role */
        $role = Role::query()->findOrFail($validated['role']);

        try {
            $grant($this->actor(), $user, $role);
        } catch (HierarchyViolationException) {
            return $this->refused($user, 'baobab::admin.users.roles.forbidden');
        }

        return $this->done($user, 'baobab::admin.users.roles.granted', $role);
    }

    public function destroy(User $user, Role $role, RevokeUserRole $revoke): RedirectResponse
    {
        try {
            $revoke($this->actor(), $user, $role);
        } catch (HierarchyViolationException) {
            return $this->refused($user, 'baobab::admin.users.roles.forbidden');
        } catch (AdminLockoutException) {
            return $this->refused($user, 'baobab::admin.users.roles.lockout');
        }

        return $this->done($user, 'baobab::admin.users.roles.revoked', $role);
    }

    private function done(User $user, string $key, Role $role): RedirectResponse
    {
        session()->flash('toast', ['type' => 'success', 'message' => __($key, ['role' => $role->name])]);

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
