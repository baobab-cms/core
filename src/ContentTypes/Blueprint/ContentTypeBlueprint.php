<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Blueprint;

use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Exceptions\UnknownRelationTargetException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Baobab\ContentTypes\Relations\RelationTargetResolver;
use Illuminate\Support\Facades\Validator;

/**
 * Représentation validée d'un blueprint de Content Type (spec 02 §1.2, §9).
 * `fields()` est validé contre le FieldRegistry (M3 point 2) : type inconnu
 * ou options invalides pour le type déclaré sont rejetés. `relations()` est
 * validé contre RelationTargetResolver (M3 point 3) : le type est déjà
 * contraint par le schéma JSON (enum fermé), seule la cible est vérifiée ici
 * (dépend de l'état de la base — hors de portée du schéma JSON).
 */
final readonly class ContentTypeBlueprint
{
    /**
     * @param  array<string, mixed>  $data  Blueprint décodé et validé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(
        string $json,
        ?BlueprintValidator $validator = null,
        ?FieldRegistry $fieldRegistry = null,
        ?RelationTargetResolver $relationTargets = null,
    ): self {
        ($validator ?? new BlueprintValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        self::validateFields($data['fields'] ?? [], $fieldRegistry ?? app(FieldRegistry::class));
        self::validateRelations($data['relations'] ?? [], $relationTargets ?? app(RelationTargetResolver::class));
        self::validateTitleField($data);

        return new self($data);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function validateFields(array $fields, FieldRegistry $registry): void
    {
        foreach ($fields as $field) {
            $type = $field['type'];

            if (! $registry->has($type)) {
                throw InvalidBlueprintException::forField(
                    "fields.{$field['key']}.type",
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
                throw InvalidBlueprintException::forField(
                    "fields.{$field['key']}.options",
                    (string) $result->errors()->first()
                );
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $relations
     */
    private static function validateRelations(array $relations, RelationTargetResolver $resolver): void
    {
        foreach ($relations as $relation) {
            try {
                $resolver->resolve((string) $relation['target']);
            } catch (UnknownRelationTargetException $e) {
                throw InvalidBlueprintException::forField(
                    "relations.{$relation['key']}.target",
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function validateTitleField(array $data): void
    {
        if (! ($data['is_addressable'] ?? false)) {
            return;
        }

        $titleField = $data['title_field'] ?? null;

        if ($titleField === null) {
            throw InvalidBlueprintException::forField(
                'title_field',
                'Un type de contenu adressable doit déclarer title_field (source du slug auto-généré, spec 02 §4.2).'
            );
        }

        /** @var list<array<string, mixed>> $fields */
        $fields = (array) ($data['fields'] ?? []);
        $field = collect($fields)->firstWhere('key', $titleField);

        if ($field === null) {
            throw InvalidBlueprintException::forField(
                'title_field',
                "Le champ « {$titleField} » n'existe pas dans fields[]."
            );
        }

        if (! in_array($field['type'], ['text', 'textarea', 'richtext'], true)) {
            throw InvalidBlueprintException::forField(
                'title_field',
                "Le champ « {$titleField} » doit être de type text, textarea ou richtext pour servir de source au slug."
            );
        }
    }

    public function key(): string
    {
        return $this->data['key'];
    }

    public function labelSingular(): string
    {
        return $this->data['label']['singular'];
    }

    public function labelPlural(): string
    {
        return $this->data['label']['plural'];
    }

    public function isAddressable(): bool
    {
        return $this->data['is_addressable'] ?? false;
    }

    public function titleField(): ?string
    {
        return $this->data['title_field'] ?? null;
    }

    /**
     * Active le workflow de validation à un niveau (spec 09 §5) : soumission,
     * approbation/rejet. Désactivé par défaut — un type simple ne passe que
     * par les transitions directes (publish/schedule/unpublish/archive).
     */
    public function workflowEnabled(): bool
    {
        return $this->data['workflow'] ?? false;
    }

    /**
     * Colonne de convention optionnelle `unpublish_at` (dépublication
     * programmée, spec 09 §4) — activée par défaut, désactivable par type.
     */
    public function unpublishAtEnabled(): bool
    {
        return $this->data['unpublish_at'] ?? true;
    }

    /**
     * Quota de révisions du type (spec 09 §6, spec 02 §9 décision 4) — `null`
     * signifie "utiliser `config('baobab.content.revisions_limit')`", `0`
     * désactive les révisions sur ce type.
     */
    public function revisionsLimit(): ?int
    {
        return $this->data['revisions']['limit'] ?? null;
    }

    /**
     * Champs exclus du snapshot de révision (spec 09 §6 : `"revisions":
     * {"except": ["view_count"]}`).
     *
     * @return list<string>
     */
    public function revisionsExcept(): array
    {
        return $this->data['revisions']['except'] ?? [];
    }

    public function blueprintVersion(): int
    {
        return $this->data['blueprint_version'] ?? 1;
    }

    /**
     * @return list<array{key: string, type: string}>
     */
    public function fields(): array
    {
        return $this->data['fields'] ?? [];
    }

    /**
     * @return list<array{key: string, type: string}>
     */
    public function relations(): array
    {
        return $this->data['relations'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
