<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\PasswordWriter;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Réinitialise un mot de passe à partir du lien reçu par e-mail (spec 04 §9,
 * décision 7). Le broker natif vérifie le jeton (existence, 60 minutes) et le
 * consomme ; toutes les sessions de l'utilisateur sont fermées.
 *
 * N'ouvre aucune session : l'utilisateur repasse par l'écran de connexion,
 * donc par le défi 2FA si son compte en a une — le lien seul ne suffit jamais
 * à entrer.
 */
final class ResetPassword
{
    public function __construct(private readonly PasswordWriter $writer) {}

    /**
     * @throws ValidationException mot de passe trop faible (`password`), ou
     *                             lien invalide ou expiré (`email`)
     */
    public function __invoke(string $email, string $token, string $password): void
    {
        Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', PasswordRule::defaults()]],
        )->validate();

        $status = Password::broker('baobab_users')->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $this->writer->write($user, $password);

                Hook::action('baobab.user.password.reset', $user);
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('baobab::admin.auth.reset_link_invalid'),
            ]);
        }
    }
}
