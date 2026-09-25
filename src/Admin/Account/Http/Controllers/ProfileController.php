<?php

declare(strict_types=1);

namespace Baobab\Admin\Account\Http\Controllers;

use Baobab\Admin\Account\Http\Requests\UpdateProfileRequest;
use Baobab\Users\Actions\RequestProfileChange;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Mon compte → Profil (spec 05 §5, décision 5 f et j) : nom et e-mail du
 * compte connecté. Adaptateur mince de `RequestProfileChange` — rien ne change
 * avant la validation par e-mail.
 *
 * Interdit pendant une impersonation : la route vit sous
 * `admin.account.profile.`, préfixe bloqué par `ImpersonationGuard`.
 */
final class ProfileController
{
    public function show(): View
    {
        $user = $this->actor();

        return view('baobab::admin.account.profile.index', [
            'user' => $user,
            'pending' => ProfileChangeRequest::query()->where('user_id', $user->getKey())->where('expires_at', '>', now())->first(),
        ]);
    }

    public function update(UpdateProfileRequest $request, RequestProfileChange $requestChange): RedirectResponse
    {
        $user = $this->actor();

        $requestChange(
            $user,
            $user,
            (string) $request->validated('name'),
            (string) $request->validated('email'),
        );

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('baobab::admin.account.profile.requested', ['email' => $user->email]),
        ]);

        return redirect()->route('admin.account.profile.show');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('baobab')->user();

        return $user;
    }
}
