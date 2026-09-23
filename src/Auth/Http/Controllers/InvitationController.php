<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

use Baobab\Auth\Http\Requests\ResetPasswordRequest;
use Baobab\Users\Actions\AcceptInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * L'invité choisit son mot de passe (spec 05 §5, décision 5). Même forme que
 * la réinitialisation — jeton, e-mail, mot de passe confirmé — d'où la
 * réutilisation de `ResetPasswordRequest`.
 */
final class InvitationController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('baobab::auth.accept-invitation', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Retour à l'écran de connexion, jamais de connexion automatique.
     */
    public function store(ResetPasswordRequest $request, AcceptInvitation $accept): RedirectResponse
    {
        $accept(
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        return redirect()->route('login')
            ->with('status', __('baobab::admin.auth.invitation_accepted'));
    }
}
