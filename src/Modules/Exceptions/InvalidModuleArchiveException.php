<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

/**
 * Refus d'une archive de module uploadée (spec 01 §2, §4 — M8 point 9, Pass B).
 *
 * Les messages sont écrits pour la personne qui a cliqué sur « Envoyer », pas
 * pour un journal : l'écran les affiche tels quels, comme il le fait déjà des
 * refus du cycle de vie.
 */
final class InvalidModuleArchiveException extends RuntimeException
{
    public static function notAZipArchive(): self
    {
        return new self("Le fichier envoyé n'est pas une archive ZIP.");
    }

    public static function unreadable(): self
    {
        return new self("L'archive est illisible ou corrompue.");
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self(sprintf(
            "L'archive dépasse la taille maximale autorisée (%s Mo).",
            number_format($maxBytes / 1024 / 1024, 0, ',', ' '),
        ));
    }

    public static function manifestMissing(): self
    {
        return new self("L'archive ne contient aucun module.json : ce n'est pas un module Baobab.");
    }

    public static function severalManifests(): self
    {
        return new self("L'archive contient plusieurs module.json : elle doit décrire un seul module.");
    }

    /**
     * Zip Slip : une entrée dont le chemin sort du dossier d'extraction
     * écrirait n'importe où sur le serveur. On refuse l'archive entière plutôt
     * que d'ignorer l'entrée — une archive qui contient ça n'est pas maladroite.
     */
    public static function unsafeEntry(string $entry): self
    {
        return new self("L'archive contient un chemin non sûr et a été refusée : {$entry}");
    }

    public static function alreadyInstalled(string $name): self
    {
        return new self("Le module {$name} existe déjà. Désinstallez-le et supprimez ses fichiers avant de le remplacer.");
    }

    public static function directoryTaken(string $directory): self
    {
        return new self("Le dossier {$directory} existe déjà sur le serveur. Il doit être libre pour recevoir l'archive.");
    }

    public static function extractionFailed(): self
    {
        return new self("L'extraction de l'archive a échoué ; rien n'a été installé.");
    }

    /**
     * L'extraction a réussi, c'est le dépôt du dossier qui a échoué. Distinguer
     * les deux n'est pas cosmétique : le message précédent envoyait chercher un
     * défaut dans l'archive alors que le problème est sur le serveur, et il a
     * coûté une session de diagnostic (suivi n° 123).
     */
    public static function depositFailed(string $root): self
    {
        return new self("Le module n'a pas pu être déposé dans {$root} ; rien n'a été installé. Vérifiez que le serveur web a les droits d'écriture sur ce dossier.");
    }
}
