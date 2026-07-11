<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Blueprint;

use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Fields\FieldRegistry;
use Illuminate\Support\Facades\Validator;

/**
 * Représentation validée d'un blueprint de Content Type (spec 02 §1.2, §9).
 * `relations()` n'est pas validé en profondeur ici — les relations (M3
 * point 3) durciront ce contrat sans changer cette enveloppe. `fields()` est
 * validé contre le FieldRegistry (M3 point 2) : type inconnu ou options
 * invalides pour le type déclaré sont rejetés.
 */
final readonly class ContentTypeBlueprint
{
    /**
     * @param  array<string, mixed>  $data  Blueprint décodé et validé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(string $json, ?BlueprintValidator $validator = null, ?FieldRegistry $fieldRegistry = null): self
    {
        ($validator ?? new BlueprintValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        self::validateFields($data['fields'] ?? [], $fieldRegistry ?? app(FieldRegistry::class));

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
