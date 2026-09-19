<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * Résultat de `ExportPersonalData` : l'archive chiffrée (fichier temporaire
 * que l'appelant déplace ou supprime) et son mot de passe, que personne d'autre
 * ne conserve — la remise (Pass D) en fait un canal distinct du lien.
 */
final readonly class PersonalDataArchive
{
    /**
     * @param  list<string>  $providers  clés des fournisseurs présents dans l'archive
     * @param  list<string>  $unsupported  clés des fournisseurs concernés mais sans export
     */
    public function __construct(
        public string $path,
        public string $password,
        public array $providers,
        public array $unsupported = [],
    ) {}
}
