<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Modules\Models\Module;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Publie les assets compilés d'un thème vers `public/themes/{slug}` (spec
 * 03 §8, §7 : « Activation ... publie les assets »). Lien symbolique
 * pointant vers le `public/` du module thème — patron `storage:link`
 * (`Illuminate\Filesystem\Filesystem::link()`, qui gère déjà la jonction
 * Windows via `mklink /J`, aucune logique cross-platform à réinventer).
 * Idempotent : ne recrée pas un lien déjà en place.
 *
 * `slugFor()` est la seule source de vérité du slug — `Baobab\View\Components\Vite`
 * l'appelle aussi pour lire le même chemin `public/themes/{slug}`. Écart
 * découvert en vérifiant : `Str::slug('vendor/name')` supprime le `/` au
 * lieu de le remplacer par le séparateur (`baobab/default-theme` →
 * `baobabdefault-theme`, pas `baobab-default-theme`) — le `/` est remplacé
 * explicitement avant slugification.
 *
 * `clearstatcache()` après la création : sur Windows, le lien est une
 * jonction créée via un process externe (`exec('mklink /J ...')`, dans
 * `Filesystem::link()`) — PHP ne sait pas qu'un `exec()` a modifié le
 * système de fichiers et garde en cache le résultat `n'existe pas` du test
 * d'idempotence précédent. Sans purge, un `File::exists()`/`is_dir()`
 * exécuté juste après dans le **même process** (un worker PHP-FPM qui sert
 * la requête d'activation puis la page suivante, par ex.) verrait un faux
 * négatif jusqu'à expiration naturelle du cache de stat.
 */
final class PublishThemeAssets
{
    public function __invoke(Module $theme): void
    {
        $source = $theme->path.'/public';

        if (! File::isDirectory($source)) {
            return;
        }

        $link = public_path('themes/'.self::slugFor($theme));

        if (File::exists($link)) {
            return;
        }

        File::ensureDirectoryExists(dirname($link));
        File::link($source, $link);
        clearstatcache(true, $link);
    }

    public static function slugFor(Module $theme): string
    {
        return Str::slug(str_replace('/', '-', $theme->name));
    }
}
