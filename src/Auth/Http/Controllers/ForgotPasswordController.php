<?php

declare(strict_types=1);

namespace Baobab\Auth\Http\Controllers;

use Baobab\Auth\Actions\SendPasswordResetLink;
use Baobab\Auth\Http\Requests\ForgotPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

final class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('baobab::auth.forgot-password');
    }

    /**
     * Toujours la même réponse, que l'adresse ait un compte ou non (spec 04
     * §9, décision 7).
     */
    public function store(ForgotPasswordRequest $request, SendPasswordResetLink $sendLink): RedirectResponse
    {
        $sendLink((string) $request->validated('email'));

        return redirect()->route('password.request')
            ->with('status', __('baobab::admin.auth.reset_link_sent'));
    }
}
