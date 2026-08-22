<?php

declare(strict_types=1);

namespace Baobab\Mail\Exceptions;

use RuntimeException;

final class InvalidMailTemplateException extends RuntimeException
{
    public static function missingDefaults(string $key, string $path): self
    {
        return new self("Template d'e-mail « {$key} » invalide — fichier de défaut introuvable ({$path}).");
    }

    public static function missingRequiredVariable(string $key, string $variable): self
    {
        return new self("Template d'e-mail « {$key} » invalide — la variable requise « {$variable} » n'apparaît pas dans le sujet/corps par défaut.");
    }

    /**
     * Validation bloquante à l'enregistrement d'une personnalisation admin
     * (spec 13 §3.3, §7 décision 3) — distincte du cas ci-dessus, qui vise le
     * défaut livré par le code : ce message-ci s'affiche à un humain en train
     * d'éditer, il nomme donc toutes les variables manquantes d'un coup plutôt
     * que de les lui faire découvrir une par une.
     *
     * @param  list<string>  $variables
     */
    public static function missingRequiredVariables(string $key, array $variables): self
    {
        $list = implode(' », « ', $variables);

        return new self("Template d'e-mail « {$key} » : la personnalisation ne peut pas être enregistrée sans la variable requise « {$list} ».");
    }

    /**
     * @param  list<string>  $variables
     */
    public static function unknownVariables(string $key, array $variables): self
    {
        $list = implode(' », « ', $variables);

        return new self("Template d'e-mail « {$key} » : la variable « {$list} » n'est pas déclarée pour ce template et resterait vide à l'envoi.");
    }
}
