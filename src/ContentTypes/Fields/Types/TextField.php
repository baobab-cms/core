<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class TextField extends FieldType
{
    public static function key(): string
    {
        return 'text';
    }

    public function columnDefinition(string $name, array $options): string
    {
        $maxLength = (int) ($options['max_length'] ?? 255);

        return "\$table->string('{$name}', {$maxLength});";
    }

    public function rules(string $name, array $options): array
    {
        return ['string', 'max:'.(int) ($options['max_length'] ?? 255)];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.text';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.text-display';
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
        return ['type' => 'string', 'maxLength' => (int) ($options['max_length'] ?? 255)];
    }

    public function optionsRules(): array
    {
        return [
            'max_length' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
