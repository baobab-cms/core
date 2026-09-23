<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Auth\PasswordWriter;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * L'invité choisit son mot de passe (spec 05 §5, décision 5) : le broker
 * `baobab_invitations` vérifie le jeton (24 heures) et le consomme ; la
 * politique de robustesse est celle de la spec 04 §9, décision 7.
 *
 * N'ouvre aucune session : l'invité se connecte ensuite, comme après une
 * réinitialisation.
 */
final class AcceptInvitation
{
    public function __construct(private readonly PasswordWriter $writer) {}

    /**
     * @throws ValidationException mot de passe trop faible (`password`), ou
     *                             lien invalide, expiré ou déjà utilisé
     *                             (`email`)
     */
    public function __invoke(string $email, string $token, string $password): void
    {
        Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', PasswordRule::defaults()]],
        )->validate();

        $status = Password::broker('baobab_invitations')->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $this->writer->write($user, $password);

                Hook::action('baobab.user.invitation.accepted', $user);
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('baobab::admin.auth.invitation_link_invalid'),
            ]);
        }
    }
}
