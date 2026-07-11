<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class MultiSelectField extends FieldType
{
    public static function key(): string
    {
        return 'multiselect';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->json('{$name}')->nullable();";
    }

    public function rules(string $name, array $options): array
    {
        return ['array'];
    }

    public function cast(array $options): string
    {
        return 'array';
    }

    public function formComponent(): string
    {
        return 'baobab::fields.multiselect';
    }

    public function displayComponent(): string
    {
        return 'baobab::fields.multiselect-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value ?? [];
    }

    public function graphqlType(array $options): string
    {
        return '[String]';
    }

    public function optionsRules(): array
    {
        return [
            'choices' => ['required', 'array', 'min:1'],
            'choices.*' => ['string'],
        ];
    }
}
