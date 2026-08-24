<?php

declare(strict_types=1);

namespace Baobab\Mail\Exceptions;

use RuntimeException;

/**
 * Un renvoi refusé, et **pourquoi** (spec 13 §4.2). Trois causes distinctes,
 * trois messages : l'écran grise déjà le bouton quand aucun resolver n'est
 * déclaré, si bien que l'exception qui remonte réellement à un humain est
 * presque toujours `sourceGone()` — le contenu a été supprimé entre l'envoi et
 * le renvoi. Un message générique lui ferait soupçonner une panne là où il n'y
 * a qu'un état du monde qui a changé.
 */
final class MailNotResendableException extends RuntimeException
{
    public static function noResolver(string $key): self
    {
        return new self("Le template « {$key} » ne déclare pas de resolver : ses e-mails ne peuvent pas être renvoyés.");
    }

    public static function invalidResolver(string $key, string $class): self
    {
        return new self("Le resolver « {$class} » déclaré par le template « {$key} » n'implémente pas le contrat attendu.");
    }

    public static function sourceGone(string $key): self
    {
        return new self("Les données de l'e-mail « {$key} » ne peuvent plus être reconstituées — la source a probablement été supprimée depuis l'envoi.");
    }
}
