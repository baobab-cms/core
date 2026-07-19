<?php

declare(strict_types=1);

namespace Baobab\Api\GraphQL\Scalars;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

/**
 * Scalaire `JSON` (M7 point 3) — pas de scalaire équivalent livré par
 * Lighthouse (contrairement à `DateTime`, voir `DateScalar`). Champ
 * `JsonField::graphqlType()` (déjà existant, spec 02) émet `'JSON'` ; passe
 * la valeur telle quelle, aucune conversion : un `json` en base est déjà
 * une structure PHP décodée par le cast Eloquent du champ.
 */
final class JsonScalar extends ScalarType
{
    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return AST::valueFromASTUntyped($valueNode, $variables);
    }
}
