<?php

declare(strict_types=1);

namespace Baobab\Api\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

/**
 * Scalaire `Time` (M7 point 3) — pas de scalaire équivalent livré par
 * Lighthouse. `TimeField::cast()` renvoie `null` (spec 02) : la colonne
 * `time` est lue telle quelle par Eloquent, une chaîne `H:i:s` déjà
 * formatée par la base — aucune conversion Carbon nécessaire, contrairement
 * à `DateScalar`.
 */
final class TimeScalar extends ScalarType
{
    public function serialize(mixed $value): string
    {
        return (string) $value;
    }

    public function parseValue(mixed $value): string
    {
        return $this->assertString($value);
    }

    /**
     * @param  array<string, mixed>|null  $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): string
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error("Query error: Can only parse strings, got {$valueNode->kind}.", $valueNode);
        }

        return $valueNode->value;
    }

    private function assertString(mixed $value): string
    {
        if (! is_string($value)) {
            throw new Error('Query error: Can only parse strings.');
        }

        return $value;
    }
}
