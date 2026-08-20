<?php

declare(strict_types=1);

namespace Baobab\Api\OpenApi;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Assemble le document OpenAPI 3.1 (M7 point 4b, spec 08 §7) : socle Core
 * (`ressources/openapi/openapi-core.json`) + `paths`/`components.schemas`
 * pour chaque Content Type dont le module est actif et l'API activée
 * (`ContentType::apiEnabled()`, même règle que REST/GraphQL). Contrairement à
 * `CompileGraphqlSchema` (patron dont s'inspire cette Action), rien n'est
 * écrit sur disque ni mis en cache ici : la documentation est consultée
 * rarement, jamais un chemin chaud comme une requête GraphQL — reconstruire
 * en mémoire à chaque appel rend « un Content Type créé apparaît dans la
 * doc immédiatement » vrai par construction, sans dépendre d'un hook de
 * recompilation. L'export sur disque (pour outillage externe/CI) est le
 * rôle de `Baobab\Console\Commands\OpenApiCompileCommand`, pas de cette
 * Action.
 */
final class CompileOpenApiSpec
{
    public function __construct(private readonly FieldRegistry $fields) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        /** @var array<string, mixed> $document */
        $document = json_decode((string) File::get(__DIR__.'/../../../ressources/openapi/openapi-core.json'), true);

        $paths = [];
        $schemas = [];

        ContentType::query()
            ->whereNotNull('module_id')
            ->whereHas('module', fn ($query) => $query->where('status', 'active'))
            ->get()
            ->filter(fn (ContentType $contentType): bool => $contentType->apiEnabled())
            ->each(function (ContentType $contentType) use (&$paths, &$schemas): void {
                $schemas[$contentType->key] = $this->schemaFor($contentType);
                $paths = array_merge($paths, $this->pathsFor($contentType));
            });

        $document['paths'] = $paths;
        $document['components']['schemas'] = array_merge($document['components']['schemas'], $schemas);

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFor(ContentType $contentType): array
    {
        $properties = ['id' => ['type' => 'integer'], 'status' => ['type' => 'string']];
        $required = [];

        foreach ($contentType->apiExposedFields() as $field) {
            $fieldType = $this->fields->resolve($field['type']);
            $schema = $fieldType->openApiSchema($field['options'] ?? []);

            if (! ($field['required'] ?? false)) {
                $schema['nullable'] = true;
            } else {
                $required[] = $field['key'];
            }

            $properties[$field['key']] = $schema;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_values(array_unique(array_merge(['id'], $required))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pathsFor(ContentType $contentType): array
    {
        $slug = Str::kebab(Str::plural($contentType->key));
        $ref = ['$ref' => "#/components/schemas/{$contentType->key}"];
        $label = $contentType->blueprint['label']['plural'] ?? $contentType->key;

        $errorResponses = [
            '401' => ['$ref' => '#/components/responses/Unauthenticated'],
            '403' => ['$ref' => '#/components/responses/Forbidden'],
            '404' => ['$ref' => '#/components/responses/NotFound'],
        ];

        return [
            "/content/{$slug}" => [
                'get' => [
                    'summary' => "Liste des entrées {$label}",
                    'tags' => [$contentType->key],
                    'responses' => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref]]]]]],
                    ] + $errorResponses,
                ],
                'post' => [
                    'summary' => "Créer une entrée {$label}",
                    'tags' => [$contentType->key],
                    'requestBody' => ['content' => ['application/json' => ['schema' => $ref]]],
                    'responses' => [
                        '201' => ['description' => 'Créé', 'content' => ['application/json' => ['schema' => $ref]]],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ] + $errorResponses,
                ],
            ],
            // `entry` est une chaîne et non un entier : la route se résout sur
            // l'identifiant public — `slug` pour un type adressable, `uuid`
            // sinon (spec 02 §4.2). La clé primaire entière n'est plus
            // acceptée.
            "/content/{$slug}/{entry}" => [
                'get' => [
                    'summary' => "Afficher une entrée {$label}",
                    'tags' => [$contentType->key],
                    'parameters' => [['name' => 'entry', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $ref]]]] + $errorResponses,
                ],
                'patch' => [
                    'summary' => "Modifier une entrée {$label} (partiel)",
                    'tags' => [$contentType->key],
                    'parameters' => [['name' => 'entry', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => $ref]]],
                    'responses' => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $ref]]],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ] + $errorResponses,
                ],
                'delete' => [
                    'summary' => "Supprimer une entrée {$label} (`?force=true` pour une purge définitive)",
                    'tags' => [$contentType->key],
                    'parameters' => [['name' => 'entry', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => ['204' => ['description' => 'Supprimé']] + $errorResponses,
                ],
            ],
            "/content/{$slug}/{entry}/publish" => [
                'post' => [
                    'summary' => "Publier une entrée {$label}",
                    'tags' => [$contentType->key],
                    'parameters' => [['name' => 'entry', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => [
                        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $ref]]],
                        '409' => ['$ref' => '#/components/responses/Conflict'],
                    ] + $errorResponses,
                ],
            ],
            "/content/{$slug}/{entry}/restore" => [
                'post' => [
                    'summary' => "Restaurer une entrée {$label} depuis la corbeille",
                    'tags' => [$contentType->key],
                    'parameters' => [['name' => 'entry', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $ref]]]] + $errorResponses,
                ],
            ],
            "/content/{$slug}/{entry}/revisions" => [
                'get' => [
                    'summary' => "Lister les révisions d'une entrée {$label}",
                    'tags' => [$contentType->key],
                    'parameters' => [['name' => 'entry', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'object']]]]]] + $errorResponses,
                ],
            ],
        ];
    }
}
