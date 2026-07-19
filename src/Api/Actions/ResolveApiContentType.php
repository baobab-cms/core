<?php

declare(strict_types=1);

namespace Baobab\Api\Actions;

use Baobab\ContentTypes\Models\ContentType;

/**
 * Résout un Content Type construit depuis le slug de route REST
 * (`/api/v1/content/{type}`), même convention que
 * `Baobab\Admin\Content\Http\Controllers\ContentController::resolveContentType()`
 * (spec 08 §2.3) — une seule Action, réutilisée par les contrôleurs de
 * lecture et d'écriture plutôt que dupliquée. `null` si le type n'existe pas,
 * n'a pas encore été généré (`module_id` absent), ou a désactivé l'API
 * (`api_enabled: false`, spec 08 §4.3, M7 point 2 Pass B — traité comme
 * inexistant, pas une erreur d'autorisation) : la décision HTTP (404) reste
 * à l'appelant, cette Action n'est pas couplée au protocole.
 */
final class ResolveApiContentType
{
    public function __invoke(string $slug): ?ContentType
    {
        $tableName = 'ct_'.str_replace('-', '_', $slug);

        $type = ContentType::where('table_name', $tableName)->first();

        if ($type === null || $type->module_id === null || ! $type->apiEnabled()) {
            return null;
        }

        return $type;
    }
}
