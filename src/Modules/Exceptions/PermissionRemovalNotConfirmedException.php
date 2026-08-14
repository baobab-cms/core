<?php

declare(strict_types=1);

namespace Baobab\Modules\Exceptions;

use RuntimeException;

/**
 * Une permission que le manifeste ne déclare plus n'est pas seulement une ligne
 * de `module_permissions` : c'est aussi la permission Spatie du même nom, et sa
 * suppression emporte en cascade toutes les attributions faites aux rôles et aux
 * utilisateurs.
 *
 * `UninstallModule` fait ce ménage sans rien demander, et c'est cohérent là-bas :
 * désinstaller est déjà un geste destructeur. Une resynchronisation ne l'est pas
 * — d'où cette confirmation, de la même forme que celle qui protège la
 * suppression d'une colonne (suivi n° 152).
 */
final class PermissionRemovalNotConfirmedException extends RuntimeException
{
    /**
     * @param  list<string>  $keys
     */
    public static function forPermissions(array $keys): self
    {
        $list = implode(', ', $keys);

        return new self(
            "Le manifeste ne déclare plus la ou les permissions « {$list} » : les retirer ".
            'révoquerait les droits déjà accordés aux rôles et aux utilisateurs — '.
            'confirmez explicitement (confirmDestructive) pour continuer.'
        );
    }
}
