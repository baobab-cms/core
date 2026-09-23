<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

use Baobab\Auth\Http\Requests\LoginRequest;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function create(): View
    {
        return view('baobab::auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = $request->boolean('remember');

        if (! Auth::guard('baobab')->validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('baobab::admin.auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        if ($user->hasTwoFactorEnabled()) {
            // Le cookie « se souvenir de moi » n'est déposé qu'après le défi
            // réussi (spec 04 §9, décision 6) : le choix l'attend en session.
            $request->session()->put('baobab.2fa.challenge_user_id', $user->id);
            $request->session()->put('baobab.2fa.remember', $remember);

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('baobab')->login($user, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('baobab')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
