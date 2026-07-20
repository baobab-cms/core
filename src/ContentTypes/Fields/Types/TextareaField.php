<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class TextareaField extends FieldType
{
    public static function key(): string
    {
        return 'textarea';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->text('{$name}');";
    }

    public function rules(string $name, array $options): array
    {
        return ['string'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.textarea';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.textarea-display';
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
        return ['type' => 'string'];
    }
}
