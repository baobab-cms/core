<?php

declare(strict_types=1);

namespace Baobab\Studio\Blueprint;

use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\Studio\Exceptions\InvalidModuleBlueprintException;
use Baobab\Studio\Exceptions\UnknownStudioRelationTargetException;
use Baobab\Studio\Relations\StudioRelationTargetResolver;
use Illuminate\Support\Facades\Validator;

/**
 * Représentation validée d'un blueprint de module Wizard Studio
 * (spec-modules §5.2, §5.4). Contrairement à `ContentTypeBlueprint` (une
 * seule entité), modélise **N entités**, chacune avec ses propres
 * `fields[]`/`relations[]` — sous-forme volontairement identique à
 * `ContentTypeBlueprint` pour permettre au futur Content Type builder (M8
 * point 2, spec-modules §6) de réutiliser `FieldRegistry`/
 * `RelationDefinitionGenerator` sans adaptation.
 *
 * Deux modes de construction : `fromJson()` (strict, schéma complet — utilisé
 * à la génération) et `fromDraftJson()` (permissif — un brouillon en cours de
 * saisie aux étapes 1-9 du wizard n'a pas à satisfaire le schéma complet,
 * seules les entités déjà déclarées sont cross-vérifiées).
 */
final readonly class ModuleBlueprint
{
    /**
     * @param  array<string, mixed>  $data  Blueprint décodé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(
        string $json,
        ?ModuleBlueprintValidator $validator = null,
        ?FieldRegistry $fieldRegistry = null,
        ?StudioRelationTargetResolver $relationTargets = null,
    ): self {
        ($validator ?? new ModuleBlueprintValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        $registry = $fieldRegistry ?? app(FieldRegistry::class);

        self::validateEntities($data['entities'] ?? [], $registry, $relationTargets ?? app(StudioRelationTargetResolver::class));
        self::validateWidgets($data['widgets'] ?? [], $registry);

        return new self($data);
    }

    /**
     * Construction permissive pour un brouillon (`ModuleBlueprintDraft`) en
     * cours de saisie : le JSON doit être un objet valide, mais aucune
     * section n'est requise — seules les entités déjà présentes sont
     * cross-vérifiées (types de champs, cibles de relation).
     */
    public static function fromDraftJson(
        string $json,
        ?FieldRegistry $fieldRegistry = null,
        ?StudioRelationTargetResolver $relationTargets = null,
    ): self {
        $data = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw InvalidModuleBlueprintException::malformedJson(json_last_error_msg());
        }

        $registry = $fieldRegistry ?? app(FieldRegistry::class);

        self::validateEntities($data['entities'] ?? [], $registry, $relationTargets ?? app(StudioRelationTargetResolver::class));
        self::validateWidgets($data['widgets'] ?? [], $registry);

        return new self($data);
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     */
    private static function validateEntities(array $entities, FieldRegistry $fieldRegistry, StudioRelationTargetResolver $relationTargets): void
    {
        $keys = [];

        foreach ($entities as $entity) {
            $key = $entity['key'] ?? null;

            if ($key !== null && in_array($key, $keys, true)) {
                throw InvalidModuleBlueprintException::forField(
                    'entities',
                    "La clé d'entité « {$key} » est déclarée plus d'une fois dans ce blueprint."
                );
            }

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        /** @var list<array{key: string, table: string}> $siblings */
        $siblings = array_map(
            static fn (array $entity): array => ['key' => $entity['key'], 'table' => $entity['table']],
            array_values(array_filter($entities, static fn (array $entity): bool => isset($entity['key'], $entity['table']))),
        );

        foreach ($entities as $entity) {
            self::validateFields($entity, $fieldRegistry);
            self::validateRelations($entity, $siblings, $relationTargets);
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private static function validateFields(array $entity, FieldRegistry $registry): void
    {
        $entityKey = $entity['key'] ?? '?';

        foreach ((array) ($entity['fields'] ?? []) as $field) {
            $type = $field['type'];

            if (! $registry->has($type)) {
                throw InvalidModuleBlueprintException::forField(
                    "entities.{$entityKey}.fields.{$field['key']}.type",
                    "Type de champ inconnu : « {$type} »."
                );
            }

            $fieldType = $registry->resolve($type);
            $optionsRules = $fieldType->optionsRules();

            if ($optionsRules === []) {
                continue;
            }

            $result = Validator::make($field['options'] ?? [], $optionsRules);

            if ($result->fails()) {
                throw InvalidModuleBlueprintException::forField(
                    "entities.{$entityKey}.fields.{$field['key']}.options",
                    (string) $result->errors()->first()
                );
            }
        }
    }

    /**
     * Réutilise `validateFields()` telle quelle : `settings_fields` d'un
     * widget a la même forme que `fields` d'une entité, donc le même
     * contrôle de type/options s'applique sans duplication de logique.
     *
     * @param  list<array<string, mixed>>  $widgets
     */
    private static function validateWidgets(array $widgets, FieldRegistry $registry): void
    {
        foreach ($widgets as $widget) {
            self::validateFields([
                'key' => $widget['key'] ?? '?',
                'fields' => $widget['settings_fields'] ?? [],
            ], $registry);
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  list<array{key: string, table: string}>  $siblings
     */
    private static function validateRelations(array $entity, array $siblings, StudioRelationTargetResolver $resolver): void
    {
        $entityKey = $entity['key'] ?? '?';

        foreach ((array) ($entity['relations'] ?? []) as $relation) {
            try {
                $resolver->resolve((string) $relation['target'], $siblings);
            } catch (UnknownStudioRelationTargetException $e) {
                throw InvalidModuleBlueprintException::forField(
                    "entities.{$entityKey}.relations.{$relation['key']}.target",
                    $e->getMessage()
                );
            }
        }
    }

    public function blueprintVersion(): int
    {
        return $this->data['blueprint_version'] ?? 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        return $this->data['identity'] ?? [];
    }

    public function name(): ?string
    {
        return $this->identity()['name'] ?? null;
    }

    public function title(): ?string
    {
        return $this->identity()['title'] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entities(): array
    {
        return $this->data['entities'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entity(string $key): ?array
    {
        foreach ($this->entities() as $entity) {
            if (($entity['key'] ?? null) === $key) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * @return array{auto_crud?: bool, custom?: list<array<string, mixed>>}
     */
    public function permissions(): array
    {
        return $this->data['permissions'] ?? [];
    }

    public function autoCrudEnabled(): bool
    {
        return $this->permissions()['auto_crud'] ?? true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customPermissions(): array
    {
        return $this->permissions()['custom'] ?? [];
    }

    /**
     * @return array{emits?: list<string>, listens?: array<string, string>}
     */
    public function hooks(): array
    {
        return $this->data['hooks'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function hooksEmitted(): array
    {
        return $this->hooks()['emits'] ?? [];
    }

    /**
     * @return array<string, string> Nom du hook → nom court de classe (sans namespace).
     */
    public function hooksListened(): array
    {
        return $this->hooks()['listens'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminMenuItems(): array
    {
        return $this->data['menus']['admin'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function widgets(): array
    {
        return $this->data['widgets'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
