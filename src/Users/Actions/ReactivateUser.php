<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Auth\Actions\DisableTwoFactor;
use Baobab\Auth\Actions\SendPasswordResetLink;
use Baobab\Facades\Hook;
use Baobab\Mail\Mailer;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Réactive un compte désactivé (spec 05 §5, décision 5 k). Le compte ne
 * revient jamais tel qu'il était : le mot de passe est invalidé et l'utilisateur
 * en choisit un nouveau par le lien envoyé (`core.password_reset`), la 2FA est
 * réinitialisée et les tokens API, révoqués à la désactivation, ne reviennent
 * pas. Les rôles et permissions sont conservés. Le motif est facultatif ;
 * l'opération est auditée par le hook `baobab.user.reactivated`.
 *
 * Réservé à un acteur de niveau strictement supérieur à la cible, comme la
 * désactivation. L'envoi du lien est dans la transaction : c'est le seul
 * chemin de retour du compte, s'il échoue rien n'est réactivé.
 */
final class ReactivateUser
{
    public function __construct(
        private readonly AccessManager $access,
        private readonly Mailer $mailer,
        private readonly DisableTwoFactor $disableTwoFactor,
    ) {}

    /**
     * @throws ValidationException motif trop long
     * @throws InvalidAccountStateException le compte n'est pas désactivé
     * @throws HierarchyViolationException cible de niveau supérieur ou égal
     */
    public function __invoke(User $actor, User $user, ?string $reason = null): void
    {
        $reason = $this->validatedReason($reason);

        if (! $user->isDeactivated()) {
            throw new InvalidAccountStateException('Only a deactivated account can be reactivated.');
        }

        $this->access->assertOutranks($actor, $user->level());

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'deactivated_at' => null,
                'deactivation_reason' => null,
                'password' => Str::random(64),
                'remember_token' => Str::random(60),
            ])->save();

            ($this->disableTwoFactor)($user);

            /** @var PasswordBroker $broker */
            $broker = Password::broker('baobab_users');
            $token = $broker->createToken($user);

            $this->mailer->send('core.password_reset', $user, [
                'reset_url' => route('password.reset', ['token' => $token, 'email' => $user->email]),
                'expires_in' => SendPasswordResetLink::LINK_LIFETIME_MINUTES,
                'user_name' => $user->name,
            ]);
        });

        Hook::action('baobab.user.reactivated', $user, $actor, $reason);
    }

    /**
     * @throws ValidationException
     */
    private function validatedReason(?string $reason): ?string
    {
        Validator::make(
            ['reason' => $reason],
            ['reason' => ['nullable', 'string', 'max:'.DeactivateUser::MAX_REASON_LENGTH]],
        )->validate();

        $reason = $reason === null ? '' : trim($reason);

        return $reason === '' ? null : $reason;
    }
}
