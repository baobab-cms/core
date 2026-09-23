<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Mail\Mailer;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Envoie le lien de réinitialisation du mot de passe (spec 04 §9, décision 7).
 *
 * Le jeton est créé et stocké par le broker natif `baobab_users` ; l'e-mail
 * part par le pipeline de la spec 13 (`core.password_reset`), jamais par la
 * notification `ResetPassword` de Laravel, pour rester personnalisable en
 * admin et tracé au journal des envois.
 *
 * Ne dit rien de l'issue : adresse inconnue, lien déjà demandé il y a moins
 * d'une minute ou envoi réel, l'appelant répond la même chose — sans quoi le
 * formulaire révélerait quelles adresses ont un compte.
 */
final class SendPasswordResetLink
{
    public const LINK_LIFETIME_MINUTES = 60;

    public function __construct(private readonly Mailer $mailer) {}

    public function __invoke(string $email): void
    {
        Password::broker('baobab_users')->sendResetLink(
            ['email' => $email],
            function (User $user, string $token): void {
                $this->mailer->send('core.password_reset', $user, [
                    'reset_url' => route('password.reset', ['token' => $token, 'email' => $user->email]),
                    'expires_in' => self::LINK_LIFETIME_MINUTES,
                    'user_name' => $user->name,
                ]);
            },
        );
    }
}
