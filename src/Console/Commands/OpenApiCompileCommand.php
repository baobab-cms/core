<?php

declare(strict_types=1);

namespace Baobab\Console\Commands;

use Baobab\Api\OpenApi\CompileOpenApiSpec;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Exporte le document OpenAPI 3.1 sur disque (M7 point 4b, spec 08 §7,
 * dernier point : « alimenteront la documentation développeur publique »)
 * — patron `GraphqlCompileCommand`, mais sans rôle de cache runtime : le
 * document est reconstruit en mémoire à chaque requête directe
 * (`GET /api/v1/openapi.json`), cette commande sert l'outillage
 * externe/CI qui a besoin d'un vrai fichier.
 */
final class OpenApiCompileCommand extends Command
{
    protected $signature = 'baobab:api:openapi';

    protected $description = 'Exporte le document OpenAPI 3.1 courant vers storage/app/baobab/openapi/openapi.json.';

    public function handle(CompileOpenApiSpec $compile): int
    {
        $path = storage_path('app/baobab/openapi/openapi.json');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, (string) json_encode($compile(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Document OpenAPI exporté : {$path}");

        return self::SUCCESS;
    }
}
