<?php

declare(strict_types=1);

namespace Baobab\Rendering\Actions;

use Illuminate\Http\Response;

/**
 * Filet d'exception admin (spec 04, suivi n° 202) — servie quand une
 * exception non gérée (ou un 403/404/419) atteint une surface admin.
 *
 * Même patron zéro-dépendance que `RenderServerError` : ni composant, ni
 * requête, ni token compilé, pour ne pas échouer précisément au moment où
 * elle est censée aider (suivi n° 242, où `<x-baobab::design-tokens />`
 * faisait tomber l'écran de connexion pendant une panne base de données).
 * Seule exception : un lien vers `admin.dashboard`, une génération d'URL
 * depuis les routes déjà chargées, sans requête ni état applicatif.
 *
 * Sur un 500, `$businessMessage` porte le message d'une exception dont la
 * classe vit sous un namespace `Baobab\*\Exceptions\*` — déjà rédigé pour
 * être lu par un humain (suivi n° 202 : « les exceptions métier obtiennent
 * un message lisible, tout le reste une page sobre, ni trace ni message
 * laissant croire à une erreur de saisie »). `null` pour tout le reste : le
 * message générique, jamais la trace ni le message brut de l'exception.
 */
final class RenderAdminError
{
    public function __invoke(int $status, ?string $businessMessage = null): Response
    {
        [$titleKey, $titleFallback, $bodyFallback] = match ($status) {
            403 => ['forbidden', 'Accès refusé', 'Vous n\'avez pas la permission d\'accéder à cette page.'],
            404 => ['not_found', 'Page introuvable', 'Cette page n\'existe pas ou plus.'],
            419 => ['session_expired', 'Session expirée', 'Votre session a expiré. Rechargez la page et réessayez.'],
            default => ['server_error', 'Une erreur est survenue', 'Une erreur inattendue s\'est produite. Réessayez, ou contactez un administrateur si le problème persiste.'],
        };

        return response(view('baobab::errors.admin', [
            'title' => self::text("errors.{$titleKey}_title", $titleFallback),
            'body' => $businessMessage ?? self::text("errors.{$titleKey}_body", $bodyFallback),
            'backToDashboard' => self::text('errors.back_to_dashboard', 'Retour au tableau de bord'),
        ])->render(), $status);
    }

    /**
     * `__()` renvoie la clé elle-même quand le fichier de langue n'a pas pu
     * être chargé (`RenderServerError`, même garde) — pas théorique sur une
     * page d'erreur.
     */
    private static function text(string $key, string $fallback): string
    {
        $translated = __("baobab::admin.{$key}");

        return is_string($translated) && $translated !== "baobab::admin.{$key}"
            ? $translated
            : $fallback;
    }
}
