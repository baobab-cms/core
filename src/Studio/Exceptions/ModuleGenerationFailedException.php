<?php

declare(strict_types=1);

namespace Baobab\Studio\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Un échec de génération au Studio, **nommé par son étape** et présentable à
 * un humain (spec-modules §5.2, §5.3 — suivi n° 147).
 *
 * Elle existe parce que la persona du Studio est explicitement
 * non-technicienne : la cause réelle est une `QueryException`, une
 * `UniqueConstraintViolationException` ou une erreur de système de fichiers,
 * dont le message porte du SQL, des valeurs et parfois le manifeste entier.
 * Aucun de ces textes ne s'affiche : ils partent au journal technique, et
 * l'écran reçoit celui-ci.
 *
 * **L'étape est l'information utile**, pas la classe de l'exception d'origine :
 * savoir que l'échec vient de l'écriture des fichiers, de l'installation, des
 * migrations ou de l'activation dit à l'utilisateur ce qu'il peut corriger —
 * un nom déjà pris, un disque plein, une entité mal décrite.
 *
 * La cause est conservée en `previous` : le journal technique et les tests y
 * accèdent, l'écran jamais.
 */
final class ModuleGenerationFailedException extends RuntimeException
{
    private function __construct(string $message, public readonly string $step, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function whileWritingFiles(string $name, Throwable $previous): self
    {
        return new self(
            "Le module « {$name} » n'a pas pu être écrit sur le disque. Vérifiez les droits d'écriture du répertoire des modules, puis relancez la génération.",
            'write',
            $previous,
        );
    }

    /**
     * Couvre l'installation **et** ses migrations : `InstallModule` les joue
     * lui-même, et l'utilisateur ne distingue pas les deux — il vient de
     * cliquer sur « Générer », pas sur « Migrer ».
     */
    public static function whileInstalling(string $name, Throwable $previous): self
    {
        return new self(
            "Le module « {$name} » a été écrit mais n'a pas pu être installé. Cela arrive quand un module porte déjà ce nom, ou qu'une entité décrit une table impossible à créer. Tout a été annulé : corrigez le blueprint et relancez.",
            'install',
            $previous,
        );
    }

    public static function whileActivating(string $name, Throwable $previous): self
    {
        return new self(
            "Le module « {$name} » a été installé mais n'a pas pu être activé. Tout a été annulé : corrigez le blueprint et relancez.",
            'activate',
            $previous,
        );
    }

    /**
     * L'échec **de la compensation**, et il se dit autrement : c'est le seul
     * cas où l'utilisateur ne peut pas simplement relancer, l'état étant resté
     * mixte. Le message le nomme au lieu de promettre une annulation qui n'a
     * pas eu lieu — mentir ici lui ferait relancer indéfiniment.
     */
    public static function afterFailedRollback(string $name, Throwable $previous): self
    {
        return new self(
            "Le module « {$name} » n'a pas pu être généré, et l'annulation a elle-même échoué : il reste probablement des fichiers ou une entrée en base. Consultez l'écran Modules pour finir de le retirer avant de relancer.",
            'rollback',
            $previous,
        );
    }
}
