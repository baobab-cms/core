<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Illuminate\Http\Response;

/**
 * Page 500 du Core (spec 19 §6.4) — servie quand une exception non gérée
 * atteint une surface publique.
 *
 * Deux propriétés, toutes deux volontaires :
 *
 * - **Aucune dépendance.** Pas de tokens compilés, pas de composant, pas de
 *   requête : une page d'erreur qui exige que le reste fonctionne échoue au
 *   moment précis où elle sert. Les libellés eux-mêmes ont un repli littéral
 *   si le chargement des traductions a échoué.
 * - **Non surchargeable par un thème.** Elle ne passe ni par la hiérarchie de
 *   templates, ni par le namespace `errors` de Laravel — que le thème actif,
 *   prépendu à `view.paths`, intercepterait. Une panne serveur se sert sans
 *   thème, puisque c'est peut-être le thème qui a planté.
 */
final class RenderServerError
{
    public function __invoke(): Response
    {
        return response(view('baobab::errors.500', [
            'title' => self::text('error_title', 'Une erreur est survenue'),
            'body' => self::text('error_body', "Le site n'a pas pu afficher cette page."),
        ])->render(), 500);
    }

    /**
     * `__()` renvoie la clé elle-même quand le fichier de langue n'a pas pu
     * être chargé. Sur cette page-là, ce cas n'est pas théorique.
     */
    private static function text(string $key, string $fallback): string
    {
        $translated = __("baobab::rendering.{$key}");

        return is_string($translated) && $translated !== "baobab::rendering.{$key}"
            ? $translated
            : $fallback;
    }
}
