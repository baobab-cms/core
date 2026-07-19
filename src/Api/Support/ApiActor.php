<?php

declare(strict_types=1);

namespace Baobab\Api\Support;

use Baobab\Users\Models\User;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Résout l'acteur d'une requête API (REST ou GraphQL) via le guard
 * `sanctum` (M7 point 2) — session admin ou Bearer token, une seule
 * résolution pour les deux, extraite de `ContentController::actor()` pour
 * être partagée avec les résolveurs GraphQL (M7 point 3) sans dupliquer le
 * contournement `forgetUser()` (mémoïsation `RequestGuard` sur un process
 * long-vécu, cf. docblock d'origine).
 */
final class ApiActor
{
    public function __invoke(): ?User
    {
        $sanctumGuard = Auth::guard('sanctum');

        if ($sanctumGuard instanceof RequestGuard) {
            $sanctumGuard->forgetUser();
        }

        /** @var User|null $user */
        $user = $sanctumGuard->user();

        return $user;
    }
}
