<?php

declare(strict_types=1);

namespace Baobab\Auth\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Table dédiée `baobab_personal_access_tokens` (spec 08 §4.1, M7 point 2) —
 * enregistrée via `Sanctum::usePersonalAccessTokenModel()`
 * (`BaobabServiceProvider::boot()`), même rationale que le renommage des
 * tables Spatie : un package installé comme dépendance Composer ne doit
 * jamais collisionner avec un usage propre de Sanctum par l'application
 * hôte.
 *
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property list<string> $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 */
final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'baobab_personal_access_tokens';
}
