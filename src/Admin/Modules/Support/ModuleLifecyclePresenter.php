<?php

declare(strict_types=1);

namespace Baobab\Admin\Modules\Support;

use Baobab\Modules\ModuleInventoryEntry;

/**
 * Traduit un état du cycle de vie en éléments d'affichage (M8 point 9, Pass A).
 *
 * Vit ici plutôt que dans la vue : un `@php` qui décide quel bouton s'affiche
 * est de la logique déguisée en gabarit, et la question « ce module peut-il
 * être activé ? » se teste, contrairement à une expression Blade.
 */
final class ModuleLifecyclePresenter
{
    /**
     * Palier de couleur du badge de statut. Volontairement neutre pour
     * `installed` : installé n'est pas un succès, c'est une étape.
     */
    public static function badgeVariant(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'inactive' => 'warning',
            default => 'neutral',
        };
    }

    /**
     * Un module dont les fichiers ont disparu ne peut plus être installé ni
     * activé — son code n'existe plus. Seul le nettoyage reste possible.
     */
    public static function canInstall(ModuleInventoryEntry $module): bool
    {
        return ! $module->isInstalled() && $module->onDisk;
    }

    /**
     * Les thèmes sont exclus : leur activation obéit à des règles propres
     * (un seul actif, emplacements, assets) que porte `ActivateTheme`, et que
     * `ActivateModule` ne connaît pas.
     */
    public static function canActivate(ModuleInventoryEntry $module): bool
    {
        return $module->isInstalled()
            && ! $module->isActive()
            && ! $module->isTheme()
            && $module->onDisk;
    }

    public static function canDeactivate(ModuleInventoryEntry $module): bool
    {
        return $module->isActive() && ! $module->isTheme();
    }

    public static function canUninstall(ModuleInventoryEntry $module): bool
    {
        return $module->isInstalled() && ! $module->isActive();
    }

    /**
     * Resynchroniser suppose les deux moitiés : une ligne en base à rafraîchir,
     * et un `module.json` sur disque d'où la rafraîchir (suivi n° 111, Pass B).
     *
     * Aucune exclusion des thèmes, contrairement à l'activation : relire un
     * manifeste ne touche ni à l'unicité du thème actif, ni aux emplacements, ni
     * aux assets — c'est `ActivateTheme` qui porte ces règles, et un thème dont
     * les tokens ont changé a autant besoin d'être relu qu'un module.
     */
    public static function canSync(ModuleInventoryEntry $module): bool
    {
        return $module->isInstalled() && $module->onDisk;
    }
}
