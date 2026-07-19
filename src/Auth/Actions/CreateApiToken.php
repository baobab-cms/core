<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\Exceptions\InvalidTokenAbilityException;
use Baobab\Users\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;

/**
 * Crée un token personnel Sanctum (spec 08 §4.1, M7 point 2) — les
 * abilities sont les permissions du référentiel unique (spec 05 §7),
 * jamais un second vocabulaire. Un token ne peut porter que des
 * permissions que son créateur possède *au moment de la création* ; s'il
 * les perd ensuite, `User::can()` le refuse déjà à la requête, sans
 * logique dédiée ici.
 */
final class CreateApiToken
{
    /**
     * @param  list<string>  $abilities
     */
    public function __invoke(User $actor, string $name, array $abilities, ?Carbon $expiresAt = null): NewAccessToken
    {
        foreach ($abilities as $ability) {
            if (! $actor->can($ability)) {
                throw InvalidTokenAbilityException::forAbility($ability);
            }
        }

        return $actor->createToken($name, $abilities, $expiresAt);
    }
}
