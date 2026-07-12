<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class DateTimeField extends FieldType
{
    public static function key(): string
    {
        return 'datetime';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->dateTime('{$name}')->nullable();";
    }

    public function rules(string $name, array $options): array
    {
        return ['date'];
    }

    public function cast(array $options): string
    {
        return 'datetime';
    }

    public function formComponent(): string
    {
        return 'baobab::field.datetime';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.datetime-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value?->toIso8601String();
    }

    public function graphqlType(array $options): string
    {
        return 'DateTime';
    }
}
