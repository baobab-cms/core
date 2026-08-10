<?php

declare(strict_types=1);

namespace Baobab\View\Support;

use BladeUI\Icons\Factory;
use Illuminate\Support\Facades\File;

/**
 * Noms d'icônes disponibles, pour alimenter une `<datalist>` de saisie assistée.
 *
 * **Pourquoi une énumération plutôt qu'un simple aperçu** : le champ d'icône
 * est un champ texte validé par motif (`bi-box-seam`), et la difficulté réelle
 * signalée en vérification navigateur n'est pas de valider ce qu'on a tapé,
 * c'est de **ne pas connaître les noms**. Une `<datalist>` filtre au clavier
 * (taper « box » réduit à une vingtaine d'entrées), ne coûte aucun JavaScript,
 * aucun endpoint, et se partage entre tous les champs d'un même écran — y
 * compris ceux répétés dans une boucle Alpine, qui pointent la même `list`.
 *
 * Ce n'est **pas** le sélecteur visuel décrit par `docs/design/direction-visuelle.md`
 * §11 (recherche, grille de prévisualisation, nom en mono sous la sélection) :
 * celui-là est un composant admin réutilisable par les quatre écrans qui
 * portent un champ d'icône, et relève de la bibliothèque de composants
 * (suivi n° 6), pas d'une passe du Studio.
 *
 * Les noms sont lus une fois puis mémorisés pour la durée de la requête : le
 * set Bootstrap Icons compte plus de deux mille fichiers, ce n'est pas un
 * balayage à refaire par champ rendu.
 *
 * @internal
 */
final class IconCatalogue
{
    /** @var array<string, list<string>>|null */
    private static ?array $cache = null;

    /**
     * Noms préfixés du set (`bi-house-door`), triés, tels que
     * `<x-baobab::icon name="…">` les attend.
     *
     * @return list<string>
     */
    public static function names(string $set = 'bi'): array
    {
        self::$cache ??= [];

        if (isset(self::$cache[$set])) {
            return self::$cache[$set];
        }

        $names = [];

        foreach (self::paths($set) as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::files($path) as $file) {
                if ($file->getExtension() === 'svg') {
                    $names[] = $set.'-'.$file->getFilenameWithoutExtension();
                }
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return self::$cache[$set] = $names;
    }

    /**
     * Répertoires déclarés par `blade-icons` pour ce **préfixe** — jamais un
     * chemin `vendor/` écrit en dur : le set peut être republié ou remplacé.
     *
     * `Factory::all()` indexe par *nom de set* (`bootstrap-icons`), pas par
     * préfixe (`bi`) : c'est le préfixe qui compose le nom d'icône, donc c'est
     * lui qu'on cherche, dans la valeur et non dans la clé.
     *
     * @return list<string>
     */
    private static function paths(string $prefix): array
    {
        /** @var array<string, array{prefix?: string, paths?: list<string>|string}> $sets */
        $sets = app(Factory::class)->all();

        foreach ($sets as $set) {
            if (($set['prefix'] ?? null) === $prefix) {
                /** @var list<string> $paths */
                $paths = (array) ($set['paths'] ?? []);

                return $paths;
            }
        }

        return [];
    }
}
