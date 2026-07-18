<?php

declare(strict_types=1);

namespace Baobab\Api\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Rendu des erreurs `/api/*` au format RFC 9457 problem+json (spec 08 §2.2)
 * — branché depuis `bootstrap/app.php` via `$exceptions->render()`, un seul
 * callback `Throwable` typé : Laravel exécute les renderers personnalisés
 * avant sa conversion interne d'`AuthenticationException`/`ValidationException`
 * (`Illuminate\Foundation\Exceptions\Handler::render()`), donc les intercepter
 * ici fonctionne. `prepareException()` a déjà converti `ModelNotFoundException`
 * en `NotFoundHttpException` et `AuthorizationException` en
 * `AccessDeniedHttpException` à ce stade — un seul cas générique
 * `HttpExceptionInterface` couvre donc aussi ces deux-là, en plus des
 * `abort_unless(..., 403|404)` déjà utilisés partout dans les contrôleurs
 * admin et repris tels quels côté REST. `null` pour tout code non couvert
 * par la spec (ex. 500) : Laravel garde son rendu JSON par défaut
 * (`shouldRenderJsonWhen` reste actif sur `api/*`), juste pas au format
 * RFC 9457 — hors périmètre de la spec §2.2, qui n'énumère que 401/403/404/
 * 409/422/429.
 */
final class ProblemDetailsRenderer
{
    /**
     * @var array<int, array{slug: string, title: string}>
     */
    private const KNOWN_STATUSES = [
        401 => ['slug' => 'unauthenticated', 'title' => 'Authentification requise.'],
        403 => ['slug' => 'forbidden', 'title' => "Vous n'avez pas la permission d'effectuer cette action."],
        404 => ['slug' => 'not-found', 'title' => 'Ressource introuvable.'],
        409 => ['slug' => 'conflict', 'title' => "Conflit avec l'état actuel de la ressource."],
        429 => ['slug' => 'rate-limited', 'title' => 'Trop de requêtes.'],
    ];

    public function render(Throwable $e): ?JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->problem($e->status, 'validation', 'Les données fournies sont invalides.', $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return $this->problem(401, 'unauthenticated', 'Authentification requise.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $known = self::KNOWN_STATUSES[$e->getStatusCode()] ?? null;

            return $known === null ? null : $this->problem($e->getStatusCode(), $known['slug'], $known['title']);
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function problem(int $status, string $slug, string $title, array $errors = []): JsonResponse
    {
        $payload = [
            'type' => "https://docs.baobabcms.com/errors/{$slug}",
            'title' => $title,
            'status' => $status,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, ['Content-Type' => 'application/problem+json']);
    }
}
