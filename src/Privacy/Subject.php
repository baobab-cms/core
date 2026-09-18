<?php

declare(strict_types=1);

namespace Baobab\Privacy;

use Baobab\Users\Models\User;
use InvalidArgumentException;

/**
 * Le sujet d'une demande RGPD (spec 16 §2.1) : un compte utilisateur et/ou
 * une adresse e-mail — les soumissions de formulaire n'ont pas de compte.
 * Value object pur, jamais de requête ici.
 */
final readonly class Subject
{
    public ?string $email;

    public function __construct(public ?int $userId = null, ?string $email = null)
    {
        $email = $email === null ? null : mb_strtolower(trim($email));
        $this->email = $email === '' ? null : $email;

        if ($this->userId === null && $this->email === null) {
            throw new InvalidArgumentException('Un sujet exige un utilisateur ou une adresse e-mail.');
        }
    }

    public static function forUser(User $user): self
    {
        return new self((int) $user->getKey(), $user->email);
    }

    public static function forEmail(string $email): self
    {
        return new self(null, $email);
    }
}
