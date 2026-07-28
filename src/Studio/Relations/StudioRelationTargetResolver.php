<?php

declare(strict_types=1);

namespace Baobab\Studio\Relations;

use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Baobab\Studio\Exceptions\UnknownStudioRelationTargetException;
use Illuminate\Support\Str;

/**
 * Résout la cible d'une relation de blueprint de module (spec-modules §5.2
 * étape 2) : une entité sœur du même blueprint (pas encore persistée nulle
 * part — target `entity:{key}`), un Content Type déjà construit
 * (`content_type:{key}`), ou un modèle Core d'une petite liste fermée
 * (`core:{Model}`). Délègue `content_type:`/`core:` à l'existant
 * `RelationTargetResolver` (M3 point 3) plutôt que de dupliquer sa logique —
 * seule la résolution `entity:` (sœur de blueprint) est propre au Studio.
 */
final class StudioRelationTargetResolver
{
    public function __construct(private readonly RelationTargetResolver $contentTypeTargets) {}

    /**
     * @param  list<array{key: string, table: string}>  $entities  Entités déclarées dans le blueprint courant.
     * @return array{class: string|null, table: string|null, key: string}
     *
     * @throws UnknownStudioRelationTargetException
     */
    public function resolve(string $target, array $entities): array
    {
        if (Str::startsWith($target, 'entity:')) {
            return $this->resolveEntity(Str::after($target, 'entity:'), $entities, $target);
        }

        if (Str::startsWith($target, 'content_type:') || Str::startsWith($target, 'core:')) {
            return $this->delegate(Str::after($target, ':'), $target);
        }

        throw UnknownStudioRelationTargetException::forTarget($target);
    }

    /**
     * @param  list<array{key: string, table: string}>  $entities
     * @return array{class: null, table: string, key: string}
     */
    private function resolveEntity(string $key, array $entities, string $target): array
    {
        foreach ($entities as $entity) {
            if ($entity['key'] === $key) {
                // La classe finale (namespace du module) n'est connue qu'à la
                // génération (Pass B) — la validation du blueprint (Pass A) n'a
                // besoin que de confirmer l'existence de l'entité sœur.
                return ['class' => null, 'table' => $entity['table'], 'key' => $key];
            }
        }

        throw UnknownStudioRelationTargetException::forTarget($target);
    }

    /**
     * @return array{class: string, table: string, key: string}
     */
    private function delegate(string $bareTarget, string $originalTarget): array
    {
        try {
            return $this->contentTypeTargets->resolve($bareTarget);
        } catch (UnknownRelationTargetException) {
            throw UnknownStudioRelationTargetException::forTarget($originalTarget);
        }
    }
}
