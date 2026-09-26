<?php

declare(strict_types=1);

namespace Baobab\Auth\Actions;

use Baobab\Auth\PasswordSetResult;
use Baobab\Auth\PasswordWriter;
use Baobab\Facades\Hook;
use Baobab\Users\Exceptions\InvalidAccountStateException;
use Baobab\Users\Exceptions\UserNotFoundException;
use Baobab\Users\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Change le mot de passe d'un utilisateur existant sans passer par l'e-mail
 * ni par son mot de passe actuel (spec 05 §6.1, commande de secours du mot de
 * passe ; suivi n° 362) : le chemin de retour d'un administrateur qui a perdu
 * son mot de passe et l'accès à sa boîte.
 *
 * Réservée au contexte console : il n'y a aucun acteur, donc aucune
 * vérification de hiérarchie — l'accès au terminal du serveur fait foi. Le
 * compte doit exister (jamais créé ici) et ne pas être désactivé : la
 * réactivation impose déjà un nouveau mot de passe (spec 05 décision 5 k).
 * Une invitation en attente est acceptée — choisir un mot de passe l'accepte,
 * comme `PasswordWriter`.
 *
 * Les effets sont ceux de tout changement de mot de passe (spec 04 §9,
 * décision 7), portés par `PasswordWriter` : sessions fermées, jeton « se
 * souvenir de moi » renouvelé. La 2FA et les tokens API sont conservés. Sans
 * mot de passe fourni, un mot de passe est forgé et rendu une seule fois. Le
 * hook porte l'origine `console` : l'audit la consigne et l'utilisateur reçoit
 * l'e-mail de sécurité habituel.
 */
final class SetUserPassword
{
    public const SOURCE = 'console';

    public function __construct(private readonly PasswordWriter $writer) {}

    /**
     * @throws UserNotFoundException aucun compte pour cette adresse
     * @throws InvalidAccountStateException le compte est désactivé
     * @throws ValidationException mot de passe fourni trop faible (`password`)
     */
    public function __invoke(string $email, ?string $password = null): PasswordSetResult
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            throw new UserNotFoundException('No account for this e-mail address.');
        }

        if ($user->isDeactivated()) {
            throw new InvalidAccountStateException('A deactivated account must be reactivated, not given a password.');
        }

        if ($password !== null) {
            Validator::make(
                ['password' => $password],
                ['password' => ['required', 'string', PasswordRule::defaults()]],
            )->validate();
        }

        $generated = $password === null ? Str::password(16) : null;

        $this->writer->write($user, $password ?? (string) $generated);

        Hook::action('baobab.user.password.changed', $user, self::SOURCE);

        return new PasswordSetResult($user, $generated);
    }
}
