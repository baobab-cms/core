<?php

declare(strict_types=1);

namespace Baobab\Mail\Exceptions;

use RuntimeException;

/**
 * Spec 13 §1 décision 3 : « un e-mail applicatif n'existe pas sans template
 * déclaré ». Levée par `TemplateRegistry` quand `Mailer::send()` est appelé
 * avec une clé qu'aucun module actif (ni le Core) n'a déclarée.
 */
final class MailTemplateNotFoundException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Aucun template d'e-mail déclaré pour la clé « {$key} ».");
    }
}
