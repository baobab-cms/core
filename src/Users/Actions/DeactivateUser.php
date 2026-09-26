<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\AdminLockoutException;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Facades\Hook;
use Baobab\Mail\Mailer;
use Baobab\Support\Logger;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Désactive un compte actif (spec 05 §5, décision 5 k) : blocage total et
 * immédiat. Le compte est marqué, son jeton « se souvenir de moi » renouvelé,
 * ses sessions fermées, ses tokens API révoqués et ses liens en attente
 * (réinitialisation de mot de passe, changement de nom ou d'e-mail) invalidés.
 * La connexion est refusée ailleurs (`deactivated_at` est une condition
 * d'authentification), et le compte n'est plus usurpable.
 *
 * Trois garde-fous : personne ne se désactive soi-même, seul un acteur de
 * niveau strictement supérieur à la cible le peut, et le dernier Super Admin
 * actif est intouchable. Le motif est facultatif ; l'opération est auditée par
 * le hook `baobab.user.deactivated`.
 *
 * Réservé à un compte actif : une invitation en attente s'annule
 * (`CancelUserInvitation`, décision 5 i), elle ne se désactive pas.
 */
final class DeactivateUser
{
    public const MAX_REASON_LENGTH = 500;

    public function __construct(
        private readonly AccessManager $access,
        private readonly Mailer $mailer,
        private readonly Logger $logger,
    ) {}

    /**
     * @throws ValidationException motif trop long
     * @throws InvalidAccountStateException le compte est déjà désactivé, ou son invitation est en attente
     * @throws HierarchyViolationException soi-même, ou une cible de niveau supérieur ou égal
     * @throws AdminLockoutException le dernier Super Admin actif
     */
    public function __invoke(User $actor, User $user, ?string $reason = null): void
    {
        $reason = $this->validatedReason($reason);

        if (! $user->isActive()) {
            throw new InvalidAccountStateException('Only an active account can be deactivated.');
        }

        if ($actor->is($user)) {
            throw new HierarchyViolationException('Cannot deactivate your own account.');
        }

        $this->access->assertOutranks($actor, $user->level());

        if ($this->access->isLastActiveSuperAdmin($user)) {
            throw new AdminLockoutException('Cannot deactivate the last active super-admin.');
        }

        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill([
                'deactivated_at' => now(),
                'deactivation_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->tokens()->delete();

            /** @var PasswordBroker $broker */
            $broker = Password::broker('baobab_users');
            $broker->deleteToken($user);

            ProfileChangeRequest::query()->where('user_id', $user->getKey())->delete();
        });

        Hook::action('baobab.user.deactivated', $user, $actor, $reason);

        // Après la validation : un échec d'envoi ne doit jamais laisser un
        // compte compromis actif. Le message ne cite pas le motif.
        try {
            $this->mailer->send('core.account_deactivated', $user, ['user_name' => $user->name]);
        } catch (Throwable $e) {
            $this->logger->error("Échec de l'envoi de l'information de désactivation.", ['user' => $user->getKey(), 'exception' => $e]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validatedReason(?string $reason): ?string
    {
        Validator::make(
            ['reason' => $reason],
            ['reason' => ['nullable', 'string', 'max:'.self::MAX_REASON_LENGTH]],
        )->validate();

        $reason = $reason === null ? '' : trim($reason);

        return $reason === '' ? null : $reason;
    }
}
