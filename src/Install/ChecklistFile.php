<?php

declare(strict_types=1);

namespace Baobab\Install;

/**
 * Un fichier que la checklist a écrit sur disque — et son contenu (spec 15 §7).
 *
 * **Le §7 veut les deux, et pour deux publics différents.** « Écrite sur
 * disque » sert celui qui a un client FTP et un fichier à téléverser
 * (arbitrage A2, n° 229) ; « affichée en clair » sert celui qui lit son écran
 * et veut voir ce qu'on lui demande de coller dans la configuration de son
 * serveur. Ne rendre que le chemin ferait de la promesse d'affichage une
 * lettre morte ; ne rendre que le contenu obligerait à le recopier à la main
 * depuis un écran.
 *
 * `path` est `null` quand l'écriture a échoué — un `storage/` non inscriptible
 * n'est pas rare sur un mutualisé. Le contenu, lui, reste : c'est précisément
 * le cas où l'affichage à l'écran est le seul moyen de récupérer les règles.
 */
final readonly class ChecklistFile
{
    /**
     * @param  string  $label  ce que l'on regarde — « Apache », « Nginx »
     * @param  string|null  $path  chemin absolu du fichier écrit, `null` si l'écriture a échoué
     */
    public function __construct(
        public string $label,
        public string $contents,
        public ?string $path = null,
    ) {}
}
