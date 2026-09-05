<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class UrlField extends FieldType
{
    public static function key(): string
    {
        return 'url';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}', 2048);";
    }

    public function rules(string $name, array $options): array
    {
        return ['string', 'url', 'max:2048'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.url';
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
        return ['type' => 'string', 'format' => 'uri', 'maxLength' => 2048];
    }
}
