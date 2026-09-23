<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

use Baobab\Auth\Actions\ResetPassword;
use Baobab\Auth\Http\Requests\ResetPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('baobab::auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Retour à l'écran de connexion, jamais de connexion automatique (spec 04
     * §9, décision 7).
     */
    public function store(ResetPasswordRequest $request, ResetPassword $reset): RedirectResponse
    {
        $reset(
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        return redirect()->route('login')
            ->with('status', __('baobab::admin.auth.password_reset_done'));
    }
}
