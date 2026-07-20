<?php

declare(strict_types=1);

namespace Baobab\Api\Http\Controllers;

use Baobab\Api\OpenApi\CompileOpenApiSpec;
use Illuminate\Http\JsonResponse;

/**
 * Document OpenAPI 3.1 servi en direct (spec 08 §7, M7 point 4b Pass A) —
 * toujours reconstruit depuis l'état courant des Content Types, jamais un
 * fichier mis en cache (voir docblock `CompileOpenApiSpec`). Route montée
 * dans le même groupe `/api/v1/*` que le reste du REST (CORS/interrupteur/
 * rate limit déjà en place, aucun nouveau middleware).
 */
final class OpenApiSpecController
{
    public function show(CompileOpenApiSpec $compile): JsonResponse
    {
        return response()->json($compile());
    }
}
