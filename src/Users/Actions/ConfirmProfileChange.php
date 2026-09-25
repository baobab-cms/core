<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Facades\Hook;
use Baobab\Mail\Mailer;
use Baobab\Users\Exceptions\ProfileChangeLinkInvalidException;
use Baobab\Users\Models\ProfileChangeRequest;
use Baobab\Users\Models\User;
use Baobab\Users\ProfileChangeOutcome;
use Baobab\Users\ProfileChangeStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Confirme un lien de changement de profil (spec 05 §5, décision 5 f-g et j).
 *
 * Le lien de l'adresse actuelle, pour un e-mail, ne change rien : il fait
 * partir le lien de la nouvelle adresse, avec un nouveau jeton (l'ancien ne
 * vaut plus). Le lien de la nouvelle adresse — ou celui de l'adresse actuelle
 * pour un nom seul — applique la demande.
 *
 * À l'application d'un e-mail : `remember_token` renouvelé et sessions du
 * compte fermées, sauf `$keepSessionId` (celle du titulaire qui confirme,
 * jamais pour « boîte perdue »). Mot de passe et 2FA ne sont pas touchés.
 */
final class ConfirmProfileChange
{
    public function __construct(private readonly Mailer $mailer) {}

    /**
     * @throws ProfileChangeLinkInvalidException lien inconnu, expiré, déjà
     *                                           utilisé ou remplacé
     * @throws ValidationException l'adresse a été prise par un autre compte
     *                             entre la demande et sa confirmation (`email`)
     */
    public function __invoke(string $token, ?string $keepSessionId = null): ProfileChangeOutcome
    {
        $request = ProfileChangeRequest::findValid($token)
            ?? throw new ProfileChangeLinkInvalidException('This profile change link is invalid or has expired.');

        $user = $request->user;

        if ($request->stage === ProfileChangeStage::Verify && $request->new_email !== null) {
            $this->handOverToNewAddress($request, $user);

            return ProfileChangeOutcome::AwaitingNewAddress;
        }

        $this->apply($request, $user, $keepSessionId);

        return ProfileChangeOutcome::Applied;
    }

    private function handOverToNewAddress(ProfileChangeRequest $request, User $user): void
    {
        $token = Str::random(64);

        DB::transaction(function () use ($request, $user, $token): void {
            $request->forceFill([
                'stage' => ProfileChangeStage::Confirm,
                'token_hash' => ProfileChangeRequest::hashToken($token),
                'expires_at' => now()->addHours(RequestProfileChange::LINK_LIFETIME_HOURS),
            ])->save();

            $this->mailer->send('core.email_change_confirm', (string) $request->new_email, [
                'confirm_url' => route('profile-change.show', ['token' => $token]),
                'expires_in' => RequestProfileChange::LINK_LIFETIME_HOURS,
                'user_name' => $user->name,
            ]);
        });

        Hook::action('baobab.user.profile.change_verified', $user, $request);
    }

    private function apply(ProfileChangeRequest $request, User $user, ?string $keepSessionId): void
    {
        $newEmail = $request->new_email;

        if ($newEmail !== null && User::query()->where('email', $newEmail)->whereKeyNot($user->getKey())->exists()) {
            $request->delete();

            throw ValidationException::withMessages(['email' => __('baobab::admin.account.profile.email_taken')]);
        }

        $before = ['name' => $user->name, 'email' => $user->email];
        $requester = $request->requester;

        DB::transaction(function () use ($request, $user, $newEmail, $keepSessionId): void {
            $attributes = [];

            if ($request->new_name !== null) {
                $attributes['name'] = $request->new_name;
            }

            if ($newEmail !== null) {
                $attributes['email'] = $newEmail;
                $attributes['remember_token'] = Str::random(60);
            }

            $user->forceFill($attributes)->save();

            if ($newEmail !== null) {
                DB::table('sessions')
                    ->where('user_id', $user->getKey())
                    ->when(! $request->forced && $keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
                    ->delete();
            }

            $request->delete();
        });

        Hook::action('baobab.user.profile.changed', $user, $before, $request, $requester);
    }
}
