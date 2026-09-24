<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Facades\Hook;
use Baobab\Users\Exceptions\InvitationNotPendingException;
use Baobab\Users\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Annule une invitation en attente (spec 05 §5, décision 5 i) : le compte,
 * qui ne s'est jamais connecté et n'a rien produit, est réellement supprimé,
 * avec son jeton d'invitation.
 *
 * Réservé aux invitations en attente : un compte actif ne se supprime pas
 * ici — l'effacement RGPD (spec 16, décisions 7-8) reste son seul chemin.
 */
final class CancelUserInvitation
{
    public function __construct(private readonly AccessManager $access) {}

    /**
     * @throws InvitationNotPendingException le compte a déjà accepté son invitation
     * @throws HierarchyViolationException
     */
    public function __invoke(User $actor, User $user): void
    {
        if (! $user->hasPendingInvitation()) {
            throw new InvitationNotPendingException('Only a pending invitation can be cancelled.');
        }

        if ($actor->is($user)) {
            throw new HierarchyViolationException('Cannot cancel your own account.');
        }

        $this->access->assertOutranks($actor, $user->level());

        $email = $user->email;
        $id = $user->id;

        DB::transaction(function () use ($user): void {
            /** @var PasswordBroker $broker */
            $broker = Password::broker('baobab_invitations');
            $broker->deleteToken($user);

            // `HasRoles` détache les rôles à la suppression du modèle.
            $user->delete();
        });

        Hook::action('baobab.user.invitation.cancelled', $email, $id, $actor);
    }
}
