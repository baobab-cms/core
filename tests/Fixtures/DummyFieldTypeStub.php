<?php

declare(strict_types=1);

namespace Baobab\Tests\Fixtures;

use Baobab\ContentTypes\Fields\FieldType;

final class DummyFieldTypeStub extends FieldType
{
    public static function key(): string
    {
        return 'dummy';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}');";
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
        return 'baobab::fields.dummy';
    }

    public function displayComponent(): string
    {
        return 'baobab::fields.dummy-display';
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
