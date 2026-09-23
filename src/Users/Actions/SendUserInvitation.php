<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Facades\Hook;
use Baobab\Mail\Mailer;
use Baobab\Users\Exceptions\InvitationNotPendingException;
use Baobab\Users\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Envoie (ou renvoie) le lien d'invitation d'un compte en attente (spec 05
 * §5, décision 5) : jeton du broker natif `baobab_invitations`, valable 24
 * heures, e-mail `core.user_invited` par le pipeline de la spec 13.
 *
 * Un nouveau jeton remplace l'ancien — un seul lien valable à la fois. Le
 * renvoi est borné par la hiérarchie (spec 05 §4.1) ; sans acteur (CLI,
 * système), aucune vérification, comme `AccessManager::assertOutranks()`.
 */
final class SendUserInvitation
{
    public const LINK_LIFETIME_HOURS = 24;

    public function __construct(
        private readonly Mailer $mailer,
        private readonly AccessManager $access,
    ) {}

    /**
     * @throws InvitationNotPendingException l'invitation a déjà été acceptée
     */
    public function __invoke(User $user, ?User $actor = null): void
    {
        if (! $user->hasPendingInvitation()) {
            throw new InvitationNotPendingException('This invitation has already been accepted.');
        }

        $this->access->assertOutranks($actor, $user->level());

        /** @var PasswordBroker $broker */
        $broker = Password::broker('baobab_invitations');
        $token = $broker->createToken($user);

        $this->mailer->send('core.user_invited', $user, [
            'accept_url' => route('invitation.accept', ['token' => $token, 'email' => $user->email]),
            'expires_in' => self::LINK_LIFETIME_HOURS,
            'inviter_name' => $actor !== null ? $actor->name : config('app.name'),
            'user_name' => $user->name,
        ]);

        Hook::action('baobab.user.invitation.sent', $user, $actor);
    }
}
