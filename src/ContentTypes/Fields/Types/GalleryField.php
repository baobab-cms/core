<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;

/**
 * Sélection ordonnée de plusieurs médias (spec 02 §3.2, spec 06 §6) —
 * structurellement différent d'`ImageField`/`FileField` : aucune colonne
 * propre sur la table du Content Type, les sélections sont matérialisées
 * dans `media_usages` (M4 point 3) avec une colonne `order`, synchronisées
 * par `SyncMediaUsagesFromEntry` sur le hook `baobab.content.saved`. Ce choix
 * (plutôt qu'une colonne JSON) fait participer la galerie à la suppression
 * protégée d'un média (`DeleteMedia`), contrairement à `image`/`file`.
 */
final class GalleryField extends FieldType
{
    public static function key(): string
    {
        return 'gallery';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return '';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function rules(string $name, array $options): array
    {
        return ['array'];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.gallery';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.gallery-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        return [];
    }

    public function graphqlType(array $options): string
    {
        return '[Media]';
    }

    /**
     * Décrit la forme voulue (patron `graphqlType()`, `[Media]`) — `toApi()`
     * renvoie aujourd'hui systématiquement `[]` (limitation REST préexistante,
     * hors périmètre de cette passe, à traiter séparément), documenter le
     * contraire serait plus trompeur que documenter l'intention du champ.
     */
    public function openApiSchema(array $options): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'url' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function optionsRules(): array
    {
        return [
            'max_items' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
