<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Controllers;

use Baobab\Admin\Account\Http\Requests\DisableTwoFactorRequest;
use Baobab\Auth\Actions\ConfirmTwoFactorCode;
use Baobab\Auth\Actions\DisableTwoFactor;
use Baobab\Auth\Actions\EnableTwoFactor;
use Baobab\Auth\Actions\RegenerateTwoFactorRecoveryCodes;
use Baobab\Auth\TwoFactorManager;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class SecurityController
{
    public function __construct(private readonly TwoFactorManager $manager) {}

    public function show(): View
    {
        $user = $this->actor();

        return view('baobab::admin.account.security.index', [
            'user' => $user,
            'qrCodeSvg' => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled()
                ? $this->manager->getQrCodeSvg($user)
                : null,
            'recoveryCodes' => session('recovery_codes'),
        ]);
    }

    public function enable(): RedirectResponse
    {
        $user = $this->actor();

        app(EnableTwoFactor::class)($user);

        session()->flash('recovery_codes', $user->two_factor_recovery_codes);
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.account.security.enabled'),
        ]);

        return redirect()->route('admin.account.security.show');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $this->actor();

        try {
            app(ConfirmTwoFactorCode::class)($user, (string) $request->input('code'));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'code' => __('baobab::admin.account.security.confirm_invalid'),
            ]);
        }

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.account.security.confirmed'),
        ]);

        return redirect()->route('admin.account.security.show');
    }

    public function regenerateRecoveryCodes(): RedirectResponse
    {
        try {
            $codes = app(RegenerateTwoFactorRecoveryCodes::class)($this->actor());
        } catch (InvalidArgumentException) {
            abort(403);
        }

        session()->flash('recovery_codes', $codes);
        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.account.security.recovery_codes_regenerated'),
        ]);

        return redirect()->route('admin.account.security.show');
    }

    public function disable(DisableTwoFactorRequest $request): RedirectResponse
    {
        app(DisableTwoFactor::class)($this->actor());

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.account.security.disabled'),
        ]);

        return redirect()->route('admin.account.security.show');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
