<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class BooleanField extends FieldType
{
    public static function key(): string
    {
        return 'boolean';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->boolean('{$name}')->default(false);";
    }

    public function rules(string $name, array $options): array
    {
        return ['boolean'];
    }

    public function cast(array $options): string
    {
        return 'boolean';
    }

    public function formComponent(): string
    {
        return 'baobab::fields.boolean';
    }

    public function displayComponent(): string
    {
        return 'baobab::fields.boolean-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return (bool) $value;
    }

    public function graphqlType(array $options): string
    {
        return 'Boolean';
    }
}
