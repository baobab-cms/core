<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

use Baobab\Users\Actions\ConfirmProfileChange;
use Baobab\Users\Exceptions\ProfileChangeLinkInvalidException;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\ProfileChangeOutcome;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Confirmation d'un changement de nom ou d'e-mail, depuis le lien reçu par
 * e-mail (spec 05 §5, décision 5 f-g et j). Le GET n'applique rien — un
 * lien ouvert par un antivirus ou un aperçu de messagerie ne doit pas
 * confirmer à la place du titulaire — c'est le POST du bouton qui le fait.
 *
 * Accessible connecté ou non : le titulaire confirme depuis sa messagerie,
 * pas forcément depuis le navigateur où il est connecté.
 */
final class ProfileChangeController extends Controller
{
    public function show(string $token): View
    {
        $pending = ProfileChangeRequest::findValid($token);

        if ($pending === null) {
            return view('baobab::auth.profile-change-result', ['status' => 'invalid']);
        }

        return view('baobab::auth.profile-change', [
            'token' => $token,
            'pending' => $pending,
        ]);
    }

    public function confirm(Request $request, ConfirmProfileChange $confirm): View
    {
        $token = (string) $request->input('token');

        // La session n'est conservée que si c'est le titulaire qui confirme.
        $pending = ProfileChangeRequest::findValid($token);
        $keepSessionId = $pending !== null && (int) Auth::guard('baobab')->id() === $pending->user_id
            ? $request->session()->getId()
            : null;

        try {
            $outcome = $confirm($token, $keepSessionId);
        } catch (ProfileChangeLinkInvalidException) {
            return view('baobab::auth.profile-change-result', ['status' => 'invalid']);
        } catch (ValidationException $e) {
            return view('baobab::auth.profile-change-result', [
                'status' => 'email_taken',
                'message' => (string) collect($e->errors())->flatten()->first(),
            ]);
        }

        return view('baobab::auth.profile-change-result', [
            'status' => $outcome === ProfileChangeOutcome::Applied ? 'applied' : 'awaiting_new_address',
        ]);
    }
}
