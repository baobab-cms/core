<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

use Baobab\Auth\Actions\RedeemTwoFactorRecoveryCode;
use Baobab\Auth\TwoFactorManager;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorManager $manager,
        private readonly RedeemTwoFactorRecoveryCode $redeemRecoveryCode,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! is_int($request->session()->get('baobab.2fa.challenge_user_id'))) {
            return redirect()->route('login');
        }

        return view('baobab::auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('baobab.2fa.challenge_user_id');

        if (! is_int($userId)) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = User::findOrFail($userId);

        // Désactivé entre le mot de passe et le défi (spec 05 §5, décision
        // 5 k) : la session du défi n'a pas d'utilisateur, la fermeture des
        // sessions ne l'a pas atteinte.
        if ($user->isDeactivated()) {
            $request->session()->forget(['baobab.2fa.challenge_user_id', 'baobab.2fa.remember']);

            return redirect()->route('login');
        }

        $recoveryCode = trim((string) $request->input('recovery_code'));
        $code = trim((string) $request->input('code'));

        $verified = $recoveryCode !== ''
            ? ($this->redeemRecoveryCode)($user, $recoveryCode)
            : $this->manager->verify($user, $code);

        if (! $verified) {
            throw ValidationException::withMessages([
                $recoveryCode !== '' ? 'recovery_code' : 'code' => __('baobab::admin.auth.two_factor_invalid'),
            ]);
        }

        $remember = $request->session()->pull('baobab.2fa.remember') === true;
        $request->session()->forget('baobab.2fa.challenge_user_id');

        Auth::guard('baobab')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
