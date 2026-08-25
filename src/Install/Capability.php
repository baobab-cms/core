<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * État d'une capacité d'hébergement (spec 15 §4.1).
 *
 * Trois états et non deux, et c'est le point important : **une capacité peut
 * être indéterminée**. Le serveur web et la disposition de la racine de
 * document ne se lisent que depuis une requête HTTP ; `php artisan
 * baobab:install` ne les connaît pas. Répondre « absente » faute de savoir
 * produirait une checklist qui affirme ce qu'elle n'a pas constaté — or c'est
 * précisément cette checklist que l'utilisateur suivra pour finir son
 * installation.
 */
enum Capability: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Unknown = 'unknown';

    public static function fromBool(bool $value): self
    {
        return $value ? self::Present : self::Absent;
    }

    public function isPresent(): bool
    {
        return $this === self::Present;
    }

    public function isKnown(): bool
    {
        return $this !== self::Unknown;
    }
}
