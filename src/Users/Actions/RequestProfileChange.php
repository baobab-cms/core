<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Facades\Hook;
use Baobab\Mail\Mailer;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\Models\User;
use Baobab\Users\ProfileChangeStage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Demande de changement de nom ou d'e-mail (spec 05 §5, décision 5 f-g et j).
 *
 * Chemin ordinaire : rien ne change avant la validation de l'adresse
 * actuelle (`core.email_change_verify`) ; pour un e-mail, la nouvelle adresse
 * ne reçoit son lien (`core.email_change_confirm`) qu'ensuite — voir
 * `ConfirmProfileChange`. Une seule demande en attente par compte : une
 * nouvelle demande remplace la précédente et invalide son lien.
 *
 * Exception « boîte perdue » (g), réservée à un admin qui domine le compte et
 * au seul e-mail : mot de passe de l'admin ressaisi, justification
 * obligatoire, la nouvelle adresse confirme seule, l'ancienne est avertie
 * (`core.email_change_warning`, à la demande — c'est ce qui laisse au
 * titulaire le temps de réagir).
 *
 * Un compte dont l'invitation est en attente n'a pas d'e-mail modifiable :
 * on annule l'invitation puis on invite la bonne adresse (décision 5 i).
 *
 * L'envoi est dans la transaction (patron `InviteUser`) : s'il échoue, aucune
 * demande n'est enregistrée.
 */
final class RequestProfileChange
{
    public const LINK_LIFETIME_HOURS = 24;

    public function __construct(
        private readonly AccessManager $access,
        private readonly Mailer $mailer,
    ) {}

    /**
     * `null` = champ non fourni (une requête partielle) ; un champ vide est
     * fourni, et refusé.
     *
     * @throws AuthorizationException l'acteur n'est ni le titulaire ni un
     *                                gestionnaire de comptes, ou « boîte perdue » sur son propre compte
     * @throws HierarchyViolationException le compte n'est pas de niveau
     *                                     strictement inférieur à celui de l'acteur
     * @throws ValidationException champ invalide, rien à changer, e-mail déjà
     *                             pris ou compte invité, et pour « boîte perdue » : mot de passe de l'admin
     *                             ou justification manquants
     */
    public function __invoke(
        User $actor,
        User $target,
        ?string $name,
        ?string $email,
        bool $lostMailbox = false,
        ?string $actorPassword = null,
        ?string $justification = null,
    ): ProfileChangeRequest {
        $isSelf = $actor->is($target);

        if (! $isSelf) {
            if (! $actor->can('baobab.users.manage')) {
                throw new AuthorizationException('Managing another account requires baobab.users.manage.');
            }

            $this->access->assertOutranks($actor, $target->level());
        }

        if ($lostMailbox && $isSelf) {
            throw new AuthorizationException('The lost-mailbox path is reserved to an admin acting on another account.');
        }

        [$data, $rules] = $this->validationInput($name, $email, $lostMailbox, $actorPassword, $justification);

        $newName = null;
        $newEmail = null;

        Validator::make($data, $rules)->after(function (ValidatorInstance $validator) use ($target, $actor, $name, $email, $lostMailbox, $actorPassword, &$newName, &$newEmail): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $newName = $name !== null && trim($name) !== $target->name ? trim($name) : null;
            $newEmail = $email !== null && mb_strtolower(trim($email)) !== mb_strtolower($target->email) ? trim($email) : null;

            if ($newName === null && $newEmail === null) {
                $validator->errors()->add($name !== null ? 'name' : 'email', __('baobab::admin.account.profile.nothing_to_change'));

                return;
            }

            if ($newEmail !== null && $target->hasPendingInvitation()) {
                $validator->errors()->add('email', __('baobab::admin.account.profile.invitation_pending'));
            }

            if ($newEmail !== null && User::query()->where('email', $newEmail)->whereKeyNot($target->getKey())->exists()) {
                $validator->errors()->add('email', __('baobab::admin.account.profile.email_taken'));
            }

            if ($lostMailbox) {
                if ($newEmail === null || $newName !== null) {
                    $validator->errors()->add('email', __('baobab::admin.users.profile.lost_mailbox_email_only'));
                }

                if (! Hash::check((string) $actorPassword, $actor->getAuthPassword())) {
                    $validator->errors()->add('admin_password', __('baobab::admin.users.profile.admin_password_invalid'));
                }
            }
        })->validate();

        $justification = $lostMailbox ? trim((string) $justification) : null;
        $token = Str::random(64);

        $request = DB::transaction(function () use ($actor, $target, $newName, $newEmail, $lostMailbox, $justification, $token): ProfileChangeRequest {
            $request = ProfileChangeRequest::query()->updateOrCreate(
                ['user_id' => $target->getKey()],
                [
                    'requested_by' => $actor->getKey(),
                    'new_name' => $newName,
                    'new_email' => $newEmail,
                    'stage' => $lostMailbox ? ProfileChangeStage::Confirm : ProfileChangeStage::Verify,
                    'forced' => $lostMailbox,
                    'justification' => $justification,
                    'token_hash' => ProfileChangeRequest::hashToken($token),
                    'expires_at' => now()->addHours(self::LINK_LIFETIME_HOURS),
                ],
            );

            if ($lostMailbox) {
                $this->mailer->send('core.email_change_confirm', (string) $newEmail, [
                    'confirm_url' => route('profile-change.show', ['token' => $token]),
                    'expires_in' => self::LINK_LIFETIME_HOURS,
                    'user_name' => $target->name,
                ]);

                $this->mailer->send('core.email_change_warning', $target, [
                    'new_email' => (string) $newEmail,
                    'actor_name' => $actor->name,
                    'changed_at' => now()->format('Y-m-d H:i'),
                ]);
            } else {
                $this->mailer->send('core.email_change_verify', $target, [
                    'confirm_url' => route('profile-change.show', ['token' => $token]),
                    'expires_in' => self::LINK_LIFETIME_HOURS,
                    'user_name' => $target->name,
                    'new_name' => $newName ?? '',
                    'new_email' => $newEmail ?? '',
                ]);
            }

            return $request;
        });

        Hook::action('baobab.user.profile.change_requested', $target, $request, $actor);

        return $request;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function validationInput(?string $name, ?string $email, bool $lostMailbox, ?string $actorPassword, ?string $justification): array
    {
        $data = [];
        $rules = [];

        if ($name !== null) {
            $data['name'] = trim($name);
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        if ($email !== null) {
            $data['email'] = trim($email);
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        if ($name === null && $email === null) {
            $data['name'] = '';
            $rules['name'] = ['required'];
        }

        if ($lostMailbox) {
            $data['admin_password'] = (string) $actorPassword;
            $rules['admin_password'] = ['required', 'string'];
            $data['justification'] = trim((string) $justification);
            $rules['justification'] = ['required', 'string', 'max:1000'];
        }

        return [$data, $rules];
    }
}
