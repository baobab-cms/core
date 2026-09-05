<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class EmailField extends FieldType
{
    public static function key(): string
    {
        return 'email';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}', 255);";
    }

    public function rules(string $name, array $options): array
    {
        return ['string', 'email', 'max:255'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.email';
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
        return ['type' => 'string', 'format' => 'email', 'maxLength' => 255];
    }
}
