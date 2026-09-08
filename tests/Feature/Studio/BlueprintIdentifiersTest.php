<?php

use Baobab\Studio\Support\BlueprintIdentifiers;

/**
 * `BlueprintIdentifiers` recopie des motifs qui appartiennent au schéma JSON.
 * La duplication est assumée (le schéma n'est appliqué qu'à la génération,
 * alors que la saisie doit refuser tout de suite), mais elle ne doit jamais
 * diverger : ce test lit le schéma sur disque et confronte chaque constante à
 * l'endroit exact dont elle est tirée.
 */
function schemaPattern(string ...$path): string
{
    /** @var array<string, mixed> $schema */
    $schema = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3).'/resources/schemas/module-blueprint.schema.json'),
        associative: true
    );

    /** @var mixed $node */
    $node = $schema;

    foreach ($path as $segment) {
        $node = $node[$segment];
    }

    return (string) $node;
}

it('keeps its PascalCase pattern in sync with the schema', function () {
    expect(BlueprintIdentifiers::CLASS_NAME)
        ->toBe('/'.schemaPattern('properties', 'entities', 'items', 'properties', 'key', 'pattern').'/')
        ->and(BlueprintIdentifiers::CLASS_NAME)
        ->toBe('/'.schemaPattern('properties', 'widgets', 'items', 'properties', 'class_name', 'pattern').'/');
});

it('keeps its snake_case pattern in sync with the schema', function () {
    expect(BlueprintIdentifiers::SNAKE)
        ->toBe('/'.schemaPattern('properties', 'entities', 'items', 'properties', 'table', 'pattern').'/')
        ->and(BlueprintIdentifiers::SNAKE)
        ->toBe('/'.schemaPattern('properties', 'entities', 'items', 'properties', 'fields', 'items', 'properties', 'key', 'pattern').'/');
});

it('keeps its hook and widget key patterns in sync with the schema', function () {
    expect(BlueprintIdentifiers::HOOK_NAME)
        ->toBe('/'.schemaPattern('properties', 'hooks', 'properties', 'emits', 'items', 'pattern').'/')
        ->and(BlueprintIdentifiers::WIDGET_KEY)
        ->toBe('/'.schemaPattern('properties', 'widgets', 'items', 'properties', 'key', 'pattern').'/');
});

it('lets an empty value through, since a draft may legitimately be incomplete', function () {
    expect(fn () => BlueprintIdentifiers::check(BlueprintIdentifiers::SNAKE, '', 'x', 'y'))
        ->not->toThrow(Exception::class);
});
