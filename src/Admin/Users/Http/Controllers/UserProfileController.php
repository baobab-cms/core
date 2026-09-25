<?php

declare(strict_types=1);

namespace Baobab\Admin\Users\Http\Controllers;

use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Admin\Users\Http\Requests\UpdateUserProfileRequest;
use Baobab\Users\Actions\RequestProfileChange;
use Baobab\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Nom et e-mail d'un compte, depuis sa fiche (spec 05 §5, décision 5 f-g et
 * j) — adaptateur mince de `RequestProfileChange`. Chemin ordinaire : la
 * demande part à l'adresse actuelle du compte ; « boîte perdue » : mot de
 * passe de l'admin et justification, la nouvelle adresse confirme seule.
 */
final class UserProfileController
{
    public function update(UpdateUserProfileRequest $request, User $user, RequestProfileChange $requestChange): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::guard('baobab')->user();

        $lostMailbox = $request->boolean('lost_mailbox');

        try {
            $requestChange(
                $actor,
                $user,
                (string) $request->validated('name'),
                (string) $request->validated('email'),
                $lostMailbox,
                $request->validated('admin_password') !== null ? (string) $request->validated('admin_password') : null,
                $request->validated('justification') !== null ? (string) $request->validated('justification') : null,
            );
        } catch (HierarchyViolationException|AuthorizationException) {
            abort(403);
        } catch (ValidationException $e) {
            throw $e->errorBag(UpdateUserProfileRequest::ERROR_BAG);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => $lostMailbox
                ? __('baobab::admin.users.profile.requested_lost_mailbox', ['email' => (string) $request->validated('email')])
                : __('baobab::admin.users.profile.requested', ['email' => $user->email]),
        ]);

        return redirect()->route('admin.users.show', ['user' => $user]);
    }
}
