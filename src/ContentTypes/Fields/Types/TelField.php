<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

/**
 * Aucune norme de numérotation n'est imposée (E.164, national...) : la
 * validation ne fait que rejeter ce qui ne peut structurellement pas être un
 * numéro de téléphone (lettres, longueur absurde), pas la forme régionale.
 */
final class TelField extends FieldType
{
    public static function key(): string
    {
        return 'tel';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->string('{$name}', 32);";
    }

    public function rules(string $name, array $options): array
    {
        return ['string', 'regex:/^[0-9+()\-.\s]{4,32}$/', 'max:32'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.tel';
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
        return ['type' => 'string', 'pattern' => '^[0-9+()\-.\s]{4,32}$', 'maxLength' => 32];
    }
}
