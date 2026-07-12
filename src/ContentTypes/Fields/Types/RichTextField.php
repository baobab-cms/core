<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;
use Mews\Purifier\Casts\CleanHtml;

/**
 * HTML nettoyé à la sauvegarde par whitelist (spec 02 §3.2) — délégué au
 * cast CastsAttributes de mews/purifier plutôt qu'à une méthode ad hoc :
 * c'est un vrai cast Eloquent, généré tel quel dans le modèle, cohérent avec
 * le reste du contrat cast().
 */
final class RichTextField extends FieldType
{
    public static function key(): string
    {
        return 'richtext';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->longText('{$name}');";
    }

    public function rules(string $name, array $options): array
    {
        return ['string'];
    }

    public function cast(array $options): string
    {
        return CleanHtml::class;
    }

    public function formComponent(): string
    {
        return 'baobab::field.richtext';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.richtext-display';
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
