<?php

declare(strict_types=1);

namespace Baobab\Users\Actions;

use Baobab\Access\AccessManager;
use Baobab\Access\Actions\AssignRole;
use Baobab\Access\Exceptions\HierarchyViolationException;
use Baobab\Facades\Hook;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Invite un utilisateur (spec 05 §5, décision 5) : le compte naît avec un
 * mot de passe aléatoire que personne ne connaît, marqué « invitation en
 * attente », puis l'invité reçoit un lien pour choisir le sien. L'acteur ne
 * connaît jamais le mot de passe.
 *
 * Le rôle doit être de niveau strictement inférieur à celui de l'acteur
 * (spec 05 §4.1). Compte, rôle et mise en file de l'e-mail forment un tout :
 * si l'un échoue, rien n'est créé.
 */
final class InviteUser
{
    public function __construct(
        private readonly AccessManager $access,
        private readonly AssignRole $assignRole,
        private readonly SendUserInvitation $sendInvitation,
    ) {}

    /**
     * @throws ValidationException nom, e-mail (déjà pris) ou rôle invalides
     * @throws HierarchyViolationException rôle de niveau supérieur ou égal à
     *                                     celui de l'acteur
     */
    public function __invoke(?User $actor, string $name, string $email, string $roleName): User
    {
        Validator::make(
            ['name' => $name, 'email' => $email, 'role' => $roleName],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'role' => ['required', 'string'],
            ],
        )->validate();

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'baobab')->first();

        if ($role === null) {
            throw ValidationException::withMessages(['role' => __('baobab::admin.users.invite.role_unknown')]);
        }

        $this->access->assertOutranks($actor, (int) $role->getAttribute('level'));

        // L'envoi est dans la transaction : s'il échoue (template absent,
        // transport mal configuré…), le compte n'est pas créé. Un compte en
        // attente sans invitation partie serait un compte que personne ne
        // sait exister — trouvé en recette le 23 septembre 2026. `Mailer`
        // ne fait que mettre le job en file : aucun envoi réel ne part pour
        // un compte ensuite annulé.
        $user = DB::transaction(function () use ($name, $email, $role, $actor): User {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(64),
                'invited_at' => now(),
            ])->save();

            ($this->assignRole)($user, $role);

            ($this->sendInvitation)($user->load('roles'), $actor);

            return $user;
        });

        Hook::action('baobab.user.invited', $user, $actor);

        return $user;
    }
}
