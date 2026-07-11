<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Blueprint;

/**
 * Représentation validée d'un blueprint de Content Type (spec 02 §1.2, §9).
 * `fields()`/`relations()` ne sont pas validés en profondeur ici — le
 * catalogue de champs (M3 point 2) et les relations (M3 point 3) durciront
 * ce contrat sans changer cette enveloppe.
 */
final readonly class ContentTypeBlueprint
{
    /**
     * @param  array<string, mixed>  $data  Blueprint décodé et validé, tel quel.
     */
    private function __construct(private array $data) {}

    public static function fromJson(string $json, ?BlueprintValidator $validator = null): self
    {
        ($validator ?? new BlueprintValidator)->validate($json);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, associative: true);

        return new self($data);
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
