<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Révoque une session d'un utilisateur. Supprimer la ligne `sessions` ne
 * suffit pas : un cookie « se souvenir de moi » reconnecterait l'appareil à
 * la requête suivante. Le jeton tourne donc aussi — Laravel n'en a qu'un par
 * utilisateur, les cookies de ses autres appareils tombent avec (spec 04 §9,
 * décision 6) ; leurs sessions ouvertes, elles, restent.
 */
final class RevokeSession
{
    public function __invoke(User $user, string $sessionId): void
    {
        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        // Même geste que `EloquentUserProvider::updateRememberToken()` :
        // nouveau jeton, sans toucher `updated_at`.
        $user->setRememberToken(Str::random(60));
        $user->timestamps = false;
        $user->save();
        $user->timestamps = true;
    }
}
