<?php

declare(strict_types=1);

namespace Baobab\Privacy;

/**
 * Ce qu'un fournisseur remet pour un sujet (spec 16 §4.1) : des données
 * sérialisables en JSON et, en plus, les fichiers binaires (médias, pièces
 * jointes), référencés par disque + chemin — leurs octets ne sont lus qu'au
 * moment d'assembler l'archive. Value object pur.
 */
final readonly class PersonalDataExport
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{disk: string, path: string}>  $files  nom de fichier dans l'archive => source
     */
    public function __construct(
        public array $data,
        public array $files = [],
    ) {}
}
