<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class DateField extends FieldType
{
    public static function key(): string
    {
        return 'date';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->date('{$name}')->nullable();";
    }

    public function rules(string $name, array $options): array
    {
        return ['date'];
    }

    public function cast(array $options): string
    {
        return 'date';
    }

    public function formComponent(): string
    {
        return 'baobab::field.date';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.date-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value?->toDateString();
    }

    public function graphqlType(array $options): string
    {
        return 'Date';
    }
}
