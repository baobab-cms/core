<?php

declare(strict_types=1);

namespace Baobab\Install\Actions;

use Baobab\Install\SuperAdminResult;
use Baobab\Users\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Étape 4 de l'installation (spec 15 §4) : le premier compte.
 *
 * **Extraite de `SuperAdminCommand` le 25 août 2026** (suivi n° 213), qui
 * portait cette logique en dur — création de l'utilisateur et assignation du
 * rôle — sans aucune Action. C'était la règle de revue n° 1 prise en défaut :
 * *une fonctionnalité sans action n'existe pas*. La commande redevient
 * l'adaptateur mince qu'elle aurait dû être, et l'installateur consomme la
 * même logique plutôt que d'en écrire une seconde. Second consommateur, donc
 * extraction et non duplication — le raisonnement du n° 206.
 *
 * **Idempotente** : sur un e-mail déjà connu, elle assigne le rôle sans
 * toucher au mot de passe. C'est ce qui permet à la commande de servir de
 * secours sur une instance vivante, et à une installation reprise de repasser
 * ici sans casser le compte qu'elle venait de créer.
 */
final class CreateSuperAdmin
{
    public const ROLE = 'super-admin';

    public const GUARD = 'baobab';

    public function __invoke(string $email, ?string $name = null, ?string $password = null): SuperAdminResult
    {
        $user = User::query()->where('email', $email)->first();
        $created = false;
        $generated = null;

        if (! $user instanceof User) {
            // Un mot de passe forgé plutôt qu'un compte sans mot de passe :
            // la commande de secours doit pouvoir créer un accès utilisable
            // sans qu'on lui en dicte un.
            $generated = $password === null ? Str::password(16) : null;

            $user = User::create([
                'name' => $name ?? 'Super Admin',
                'email' => $email,
                'password' => $password ?? $generated,
            ]);

            $created = true;
        }

        $role = Role::findOrCreate(self::ROLE, self::GUARD);

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return new SuperAdminResult($user, $created, $generated);
    }
}
