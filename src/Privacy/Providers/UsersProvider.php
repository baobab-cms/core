<?php

declare(strict_types=1);

namespace Baobab\Privacy\Providers;

use Baobab\Auth\Actions\ListActiveSessions;
use Baobab\Privacy\DataDeclaration;
use Baobab\Privacy\PersonalDataExport;
use Baobab\Privacy\Subject;
use Baobab\Users\Models\User;
use Illuminate\Support\Carbon;

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
        return $this->userIdOf($subject) !== null;
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
