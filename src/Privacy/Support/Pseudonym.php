<?php

declare(strict_types=1);

namespace Baobab\Privacy\Support;

/**
 * Le pseudonyme d'une adresse e-mail (spec 16 §4.3) : un HMAC sur la clé
 * applicative, donc stable pour une même adresse (le compte fantôme et les
 * lignes de `mail_log` de la même personne se recoupent) et invérifiable sans
 * cette clé. Une adresse pseudonymisée porte le domaine réservé
 * `erased.invalid`, qui ne peut jamais recevoir de courrier.
 */
final class Pseudonym
{
    public const DOMAIN = 'erased.invalid';

    public static function of(string $email): string
    {
        return substr(hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key')), 0, 12);
    }

    public static function address(string $email): string
    {
        return self::of($email).'@'.self::DOMAIN;
    }

    public static function isPseudonymized(string $email): bool
    {
        return str_ends_with(mb_strtolower($email), '@'.self::DOMAIN);
    }
}
