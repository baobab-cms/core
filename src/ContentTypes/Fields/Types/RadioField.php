<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

/**
 * Même stockage que SelectField (spec 02 §3.2 : « Comme select, rendu
 * différent ») — seuls formComponent()/displayComponent() diffèrent.
 */
final class RadioField extends FieldType
{
    public static function key(): string
    {
        return 'radio';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}')->nullable();";
    }

    public function rules(string $name, array $options): array
    {
        /** @var list<string> $choices */
        $choices = $options['choices'] ?? [];

        return ['string', 'in:'.implode(',', $choices)];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.radio';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.radio-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return $value;
    }

    public function graphqlType(array $options): string
    {
        return 'String';
    }

    public function optionsRules(): array
    {
        return [
            'choices' => ['required', 'array', 'min:1'],
            'choices.*' => ['string'],
        ];
    }
}
