<?php

declare(strict_types=1);

namespace Baobab\Themes\Actions;

use Baobab\Actions\Modules\DeactivateModule;
use Baobab\Modules\Exceptions\ModuleNotFoundException;
use Baobab\Modules\Models\Module;
use Baobab\Themes\Exceptions\NotAThemeException;

/**
 * Désactive le thème actif et ramène le site au rendu de repli du Core
 * (spec 19 §6.1, qui qualifie l'état « aucun thème actif » de transitoire et
 * signalé à qui peut agir — jamais d'interdit). Miroir d'`ActivateTheme`,
 * dont elle reprend la délégation au cycle de vie générique des modules
 * plutôt que d'en dupliquer la logique de dépendances.
 *
 * **Pourquoi une action, alors que le geste manquant était d'interface**
 * (n° 126) : `DeactivateModule` accepte n'importe quel module, et la route de
 * cet écran est gouvernée par `baobab.system.themes.manage`. Sans la garde de
 * type, cette permission désactiverait n'importe quel module du site sans
 * jamais passer par `baobab.system.modules.manage` — l'écran des thèmes
 * deviendrait un chemin détourné vers le cycle de vie des modules. La règle
 * appartient donc au domaine des thèmes, où elle se teste, exactement comme
 * la garde symétrique d'`ActivateTheme`.
 *
 * **Ce qu'elle ne fait délibérément pas : dépublier les assets.** La symétrie
 * avec `ActivateTheme`, qui les publie, est trompeuse — `public/themes/{slug}`
 * sert aussi la prévisualisation d'un thème inactif (n° 125). Les retirer ici
 * rendrait tout thème désactivé prévisualisable sans sa feuille de style,
 * c'est-à-dire le défaut qu'on venait de corriger. Ils partent à la
 * désinstallation (`UnpublishThemeAssets`), qui est le moment où le thème
 * cesse vraiment d'exister.
 *
 * Les emplacements de menus et zones de widgets ne sont pas davantage
 * réinitialisés : ils deviennent orphelins, l'état que `SyncThemeLocations`
 * traite déjà quand un thème est remplacé par un autre aux clés différentes.
 */
final class DeactivateTheme
{
    public function __construct(private readonly DeactivateModule $deactivate) {}

    public function __invoke(string $name): Module
    {
        $module = Module::where('name', $name)->first();

        if (! $module instanceof Module) {
            throw ModuleNotFoundException::named($name);
        }

        if ($module->type !== 'theme') {
            throw NotAThemeException::forModule($name);
        }

        return ($this->deactivate)($name);
    }
}
