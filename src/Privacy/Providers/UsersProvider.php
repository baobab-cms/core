<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Access\Models\DirectPermissionGrant;
use Baobab\Auth\Actions\ListActiveSessions;
use Baobab\ContentTypes\Editorial\Models\ContentLock;
use Baobab\Notify\Models\NotificationPreference;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\EraseOutcome;
use Baobab\Privacy\EraseReport;
use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\Subject;
use Baobab\Privacy\Support\Pseudonym;
use Baobab\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** `core.users` (spec 16 §2.1) : le profil du compte. */
final class UsersProvider extends CoreProvider
{
    public function key(): string
    {
        return 'core.users';
    }

    public function describe(): DataDeclaration
    {
        return new DataDeclaration(
            title: __('baobab::privacy.users.title'),
            nature: __('baobab::privacy.users.nature'),
            purpose: __('baobab::privacy.users.purpose'),
            legalBasis: __('baobab::privacy.users.legal_basis'),
            retention: __('baobab::privacy.users.retention'),
            externalServices: $this->mailServices(),
        );
    }

    public function locate(Subject $subject): bool
    {
        $userId = $this->userIdOf($subject);

        // Un compte déjà anonymisé n'est plus « à effacer » : idempotence.
        return $userId !== null
            && ! Pseudonym::isPseudonymized((string) User::query()->whereKey($userId)->value('email'));
    }

    /**
     * Le compte devient son propre fantôme (spec 16 §4.3, décision 8) : les
     * références (audit, médias, contenus, révisions) le désignent déjà et
     * n'ont rien à être rebasculées. Plus aucune façon de s'y connecter ni
     * de recevoir un courrier : mot de passe irrécupérable, secrets, sessions,
     * jetons, droits et préférences retirés.
     */
    public function erase(Subject $subject): EraseReport
    {
        $user = User::query()->findOrFail($this->userIdOf($subject));
        $originalEmail = $user->email;
        $hash = Pseudonym::of($originalEmail);

        $user->forceFill([
            'name' => __('baobab::privacy.erasure.ghost_name', ['hash' => $hash]),
            'email' => Pseudonym::address($originalEmail),
            'email_verified_at' => null,
            'password' => Str::random(64),
            'remember_token' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            // Le motif d'une désactivation (spec 05 §5, décision 5 k) est une
            // donnée du sujet ; la date, elle, ne dit rien de personne.
            'deactivation_reason' => null,
        ])->save();

        $user->syncRoles([]);
        $user->syncPermissions([]);
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();

        // Table du squelette Laravel, absente d'une installation qui n'a pas de
        // réinitialisation de mot de passe par jeton.
        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();
        }

        NotificationPreference::query()->where('user_id', $user->getKey())->delete();
        ContentLock::query()->where('user_id', $user->getKey())->delete();
        DirectPermissionGrant::query()->where('user_id', $user->getKey())->delete();

        return new EraseReport(EraseOutcome::Anonymized, 1, __('baobab::privacy.erasure.users_note'));
    }

    /**
     * Le profil et les sessions actives ; jamais le hash du mot de passe ni
     * les secrets 2FA (aucune valeur n'est utile à la personne, tous sont des
     * secrets d'authentification), seulement le fait que la 2FA soit active.
     */
    public function export(Subject $subject): PersonalDataExport
    {
        $user = User::query()->findOrFail($this->userIdOf($subject));

        return new PersonalDataExport([
            'profile' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'deactivation_reason' => $user->deactivation_reason,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'roles' => $user->getRoleNames()->all(),
            'sessions' => (new ListActiveSessions)($user)->map(static fn (object $session): array => [
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => Carbon::createFromTimestamp((int) $session->last_activity)->toIso8601String(),
            ])->all(),
        ]);
    }
}
