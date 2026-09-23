<?php

declare(strict_types=1);

namespace Baobab\Auth;

use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ce que tout changement de mot de passe entraîne (spec 04 §9, décision 7),
 * partagé par `ResetPassword` et `ChangePassword` : le nouveau mot de passe
 * (haché par le cast `hashed` du modèle), un nouveau `remember_token` — qui
 * invalide les cookies « se souvenir de moi » de tous les appareils — et la
 * fermeture des sessions ouvertes, sauf `$keepSessionId`.
 *
 * Les sessions vivent dans la table native `sessions` (sans modèle, même
 * patron que `RevokeSession`).
 */
final class PasswordWriter
{
    public function write(User $user, string $password, ?string $keepSessionId = null): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
