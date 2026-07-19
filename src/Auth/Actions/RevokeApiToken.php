<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Révoque un token personnel (spec 08 §4.1 : « révocation individuelle »).
 * Type de base Sanctum, pas `Baobab\Auth\Models\PersonalAccessToken` : le
 * modèle réellement utilisé au runtime (table `baobab_personal_access_tokens`)
 * est configuré dynamiquement via `Sanctum::usePersonalAccessTokenModel()`
 * (`BaobabServiceProvider::boot()`), invisible pour PHPStan à travers le
 * type générique `TToken` de `HasApiTokens::tokens()` — seule cette classe
 * de base reste statiquement vérifiable, et `delete()` (Eloquent) n'a de
 * toute façon pas besoin de la sous-classe.
 */
final class RevokeApiToken
{
    public function __invoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }
}
