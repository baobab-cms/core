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
     * Les classes déclarées par `resolver` et `sample` (§3.1, Pass B1) sont
     * vérifiées à l'installation du module, jamais au moment de servir un
     * écran : une classe absente ou qui n'implémente pas le contrat est une
     * erreur du module, pas une situation d'exécution à contourner. Le
     * message nomme l'attribut fautif, sans quoi un module qui déclare les
     * deux laisserait chercher lequel est en cause.
     */
    public static function unknownClass(string $key, string $attribute, string $class): self
    {
        return new self("Template d'e-mail « {$key} » invalide — la classe « {$class} » déclarée en `{$attribute}` est introuvable.");
    }

    public static function wrongContract(string $key, string $attribute, string $class, string $contract): self
    {
        return new self("Template d'e-mail « {$key} » invalide — la classe « {$class} » déclarée en `{$attribute}` doit implémenter {$contract}.");
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
