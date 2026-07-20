<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class TimeField extends FieldType
{
    public static function key(): string
    {
        return 'time';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->time('{$name}')->nullable();";
    }

    public function rules(string $name, array $options): array
    {
        return ['date_format:H:i:s'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.time';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.time-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value;
    }

    public function graphqlType(array $options): string
    {
        return 'Time';
    }

    public function openApiSchema(array $options): array
    {
        return ['type' => 'string', 'pattern' => '^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$', 'example' => '14:30:00'];
    }
}
