<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

final class SlugField extends FieldType
{
    public static function key(): string
    {
        return 'slug';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}')->unique();";
    }

    public function rules(string $name, array $options): array
    {
        return ['string', 'alpha_dash'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::fields.slug';
    }

    public function displayComponent(): string
    {
        return 'baobab::fields.slug-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value;
    }

    public function graphqlType(array $options): string
    {
        return 'String';
    }
}
