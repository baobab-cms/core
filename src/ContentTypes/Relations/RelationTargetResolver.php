<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Relations;

use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Models\ContentType;
use Baobab\Users\Models\User;

/**
 * Résout la cible d'une relation (spec 02 §5) : un autre Content Type déjà
 * construit, ou un modèle Core d'une petite liste fermée. Cibler un modèle
 * exposé par un module (« relationnable » dans son manifest) est différé —
 * aucun consommateur actuel, voir le plan de M3 point 3.
 */
final class RelationTargetResolver
{
    /**
     * @var array<string, array{class: string, table: string}>
     */
    private const CORE_MODELS = [
        'User' => ['class' => User::class, 'table' => 'users'],
    ];

    /**
     * @return array{class: string, table: string, key: string}
     */
    public function resolve(string $target): array
    {
        $contentType = ContentType::where('key', $target)->first();

        if ($contentType instanceof ContentType && $contentType->module_id !== null) {
            return [
                'class' => $contentType->modelClass(),
                'table' => $contentType->table_name,
                'key' => $contentType->key,
            ];
        }

        if (isset(self::CORE_MODELS[$target])) {
            return [...self::CORE_MODELS[$target], 'key' => $target];
        }

        throw UnknownRelationTargetException::forTarget($target);
    }
}
