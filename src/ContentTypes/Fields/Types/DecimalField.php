<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class DecimalField extends FieldType
{
    public static function key(): string
    {
        return 'decimal';
    }

    public function columnDefinition(string $name, array $options): string
    {
        $precision = (int) ($options['precision'] ?? 10);
        $scale = (int) ($options['scale'] ?? 2);

        return "\$table->decimal('{$name}', {$precision}, {$scale});";
    }

    public function rules(string $name, array $options): array
    {
        $rules = ['numeric'];

        if (isset($options['min'])) {
            $rules[] = 'min:'.$options['min'];
        }

        if (isset($options['max'])) {
            $rules[] = 'max:'.$options['max'];
        }

        return $rules;
    }

    public function cast(array $options): string
    {
        return 'decimal:'.(int) ($options['scale'] ?? 2);
    }

    public function formComponent(): string
    {
        return 'baobab::field.decimal';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.decimal-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value === null ? null : (float) $value;
    }

    public function graphqlType(array $options): string
    {
        return 'Float';
    }

    public function optionsRules(): array
    {
        return [
            'precision' => ['nullable', 'integer', 'min:1'],
            'scale' => ['nullable', 'integer', 'min:0'],
            'min' => ['nullable', 'numeric'],
            'max' => ['nullable', 'numeric', 'gte:min'],
        ];
    }
}
