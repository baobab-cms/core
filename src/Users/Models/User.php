<?php

declare(strict_types=1);

namespace Baobab\Users\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request as RequestFacade;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $invited_at
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected $table = 'users';

    protected string $guard_name = 'baobab';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Invité depuis l'admin ou l'API sans avoir encore choisi son mot de
     * passe (spec 05 §5, décision 5) : le compte existe, mais ne peut pas
     * se connecter.
     */
    public function hasPendingInvitation(): bool
    {
        return $this->invited_at !== null;
    }

    /**
     * Le niveau d'un utilisateur = le niveau maximal de ses rôles (spec 05 §4.1).
     */
    public function level(): int
    {
        return (int) $this->roles->max('level');
    }

    /**
     * Restreint toute vérification de permission aux abilities du token
     * Sanctum courant (spec 08 §4.1 : « abilities = permissions du
     * référentiel unique »), sans jamais toucher aux Policies générées —
     * celles-ci délèguent déjà à un test de permission brut
     * (`$user->can('content.{type}.update_any')`), qui repasse ici. `parent::can()`
     * reste l'unique source de vérité sur ce que l'utilisateur peut faire
     * *maintenant* : le token ne peut que restreindre, jamais élargir, et
     * s'il perd une permission entre-temps, `parent::can()` la refuse déjà
     * avant même de regarder le token — satisfait « perd la permission si
     * son créateur la perd » sans logique dédiée. Ne filtre que les
     * chaînes de permission brutes (`domaine.objet.action`, convention
     * CLAUDE.md — reconnues à leur point) ; un nom d'ability Gate
     * (`'create'`, `'update'`...) passe sans opinion, il se résout
     * normalement vers une méthode de Policy qui rappelle `$user->can('...')`
     * en interne, où la restriction s'applique alors au bon niveau.
     * `currentAccessToken()` vaut `null` hors du guard `sanctum` (routes
     * admin, toujours `auth:baobab` direct) — comportement inchangé ; pour
     * une requête de session passée par le guard `sanctum`, Sanctum pose un
     * `TransientToken` dont `can()` répond toujours vrai (accès complet,
     * comme aujourd'hui) ; seul un vrai Bearer token restreint réellement.
     *
     * @param  iterable<array-key, string>|string  $abilities
     * @param  array<array-key, mixed>|mixed  $arguments
     */
    public function can($abilities, $arguments = []): bool
    {
        if (! parent::can($abilities, $arguments)) {
            return false;
        }

        // Discriminant sur la requête (`bearerToken()`, réellement typée
        // nullable par Laravel), pas sur `currentAccessToken()` : ce
        // dernier est typé `@return TToken` (jamais nullable) par le trait
        // Sanctum — imprécis, la propriété sous-jacente est en réalité non
        // typée et vaut `null` par défaut hors du guard `sanctum` (routes
        // admin, guard `baobab` direct) — un `=== null`/`instanceof`/`?->`
        // dessus se heurte donc à ce docblock erroné, quelle que soit la
        // formulation. Si aucun Bearer token n'est présent sur la requête,
        // aucune restriction (session, comportement Pass A/B inchangé) ;
        // sinon le guard a déjà validé et attaché un vrai token avant que
        // ce `can()` ne s'exécute, `currentAccessToken()` est fiable ici.
        if (RequestFacade::bearerToken() === null) {
            return true;
        }

        $token = $this->currentAccessToken();

        foreach ((array) $abilities as $ability) {
            if (str_contains($ability, '.') && ! $token->can($ability)) {
                return false;
            }
        }

        return true;
    }
}
