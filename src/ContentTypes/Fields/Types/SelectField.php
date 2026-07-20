<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class SelectField extends FieldType
{
    public static function key(): string
    {
        return 'select';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}')->nullable();";
    }

    public function rules(string $name, array $options): array
    {
        /** @var list<string> $choices */
        $choices = $options['choices'] ?? [];

        return ['string', 'in:'.implode(',', $choices)];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.select';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.select-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value;
    }

    public function graphqlType(array $options): string
    {
        return 'String';
    }

    public function openApiSchema(array $options): array
    {
        return ['type' => 'string', 'enum' => $options['choices'] ?? []];
    }

    public function optionsRules(): array
    {
        return [
            'choices' => ['required', 'array', 'min:1'],
            'choices.*' => ['string'],
        ];
    }
}
