<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\PasswordWriter;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Change le mot de passe de l'utilisateur connecté (spec 04 §9, décision 7).
 *
 * Le mot de passe actuel est vérifié ici, pas seulement dans le Form Request :
 * l'écran de compte n'est qu'un client possible. Les autres sessions sont
 * fermées, `$keepSessionId` (celle qui fait la demande) est conservée.
 *
 * Interdit pendant une impersonation (spec 04 §9.1) : la route vit sous
 * `admin.account.security.`, préfixe bloqué par `ImpersonationGuard`.
 */
final class ChangePassword
{
    public function __construct(private readonly PasswordWriter $writer) {}

    /**
     * @throws ValidationException mot de passe actuel erroné
     *                             (`current_password`) ou nouveau mot de
     *                             passe trop faible (`password`)
     */
    public function __invoke(User $user, string $currentPassword, string $newPassword, ?string $keepSessionId = null): void
    {
        Validator::make(
            ['current_password' => $currentPassword, 'password' => $newPassword],
            [
                'current_password' => ['required', 'string'],
                'password' => ['required', 'string', PasswordRule::defaults()],
            ],
        )->after(function (ValidatorInstance $validator) use ($user, $currentPassword): void {
            if ($currentPassword !== '' && ! Hash::check($currentPassword, $user->getAuthPassword())) {
                $validator->errors()->add('current_password', __('baobab::admin.account.security.current_password_invalid'));
            }
        })->validate();

        $this->writer->write($user, $newPassword, $keepSessionId);

        Hook::action('baobab.user.password.changed', $user);
    }
}
