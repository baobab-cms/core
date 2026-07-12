<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class IntegerField extends FieldType
{
    public static function key(): string
    {
        return 'integer';
    }

    public function columnDefinition(string $name, array $options): string
    {
        $unsigned = (bool) ($options['unsigned'] ?? false);
        $big = (bool) ($options['big'] ?? false);

        $method = $big ? 'bigInteger' : 'integer';
        $method = $unsigned ? 'unsigned'.ucfirst($method) : $method;

        return "\$table->{$method}('{$name}');";
    }

    public function rules(string $name, array $options): array
    {
        $rules = ['integer'];

        if (isset($options['min'])) {
            $rules[] = 'min:'.(int) $options['min'];
        }

        if (isset($options['max'])) {
            $rules[] = 'max:'.(int) $options['max'];
        }

        return $rules;
    }

    public function cast(array $options): string
    {
        return 'integer';
    }

    public function formComponent(): string
    {
        return 'baobab::field.integer';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.integer-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value === null ? null : (int) $value;
    }

    public function graphqlType(array $options): string
    {
        return 'Int';
    }

    public function optionsRules(): array
    {
        return [
            'unsigned' => ['nullable', 'boolean'],
            'big' => ['nullable', 'boolean'],
            'min' => ['nullable', 'integer'],
            'max' => ['nullable', 'integer', 'gte:min'],
        ];
    }
}
