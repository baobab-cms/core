<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

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
    public function __construct(private readonly TwoFactorManager $manager) {}

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

        $request->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = User::findOrFail($userId);

        if (! $this->manager->verify($user, (string) $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('baobab::admin.auth.two_factor_invalid'),
            ]);
        }

        $request->session()->forget('baobab.2fa.challenge_user_id');

        Auth::guard('baobab')->login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
