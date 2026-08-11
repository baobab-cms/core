<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Modules\Models\Module;

/**
 * Retire le lien `public/themes/{slug}` posé par `PublishThemeAssets`
 * (spec 01 §3 : la désinstallation supprime « les permissions, les entrées de
 * menus, **les assets publiés** »).
 *
 * Écart relevé le 10 août 2026 en livrant la Pass A du M8 point 9 : les trois
 * premiers nettoyages existaient depuis M1, le quatrième n'avait jamais été
 * écrit — désinstaller un thème laissait une jonction morte dans `public/`.
 *
 * `slugFor()` de `PublishThemeAssets` reste la source unique du slug : on
 * supprime exactement ce que la publication a créé, jamais un chemin
 * recalculé autrement. Idempotent, et sans effet sur un module qui n'est pas
 * un thème — la désinstallation l'appelle sans condition.
 *
 * **Jamais `File::deleteDirectory()`** : la cible est un lien vers le `public/`
 * du thème, et effacer récursivement à travers lui détruirait les fichiers du
 * module eux-mêmes. On retire le lien, pas ce qu'il désigne.
 *
 * La distinction `rmdir`/`unlink` suit le système, pas le type détecté : sur
 * Windows le lien est une **jonction de répertoire** (`mklink /J`, posée par
 * `Filesystem::link()`), qu'`unlink()` refuse et que `rmdir()` retire sans
 * toucher à sa cible ; ailleurs c'est un lien symbolique, qu'`unlink()` retire.
 * `is_link()` n'est pas fiable pour trancher — le test de `PublishThemeAssets`
 * documente déjà son incohérence sur les jonctions Windows, et le helper de
 * test `removeThemeLink()` avait tranché de la même façon avant cette Action.
 */
final class UnpublishThemeAssets
{
    public function __invoke(Module $theme): void
    {
        if ($theme->type !== 'theme') {
            return;
        }

        $link = public_path('themes/'.PublishThemeAssets::slugFor($theme));

        // `file_exists()` suit la jonction et reste fiable là où `File::exists()`
        // et `is_link()` divergent (même écart que dans PublishThemeAssets).
        if (! file_exists($link)) {
            return;
        }

        if (windows_os()) {
            @rmdir($link);
        } else {
            @unlink($link);
        }

        clearstatcache(true, $link);
    }
}
