<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;
use Baobab\Media\Models\Media;

/**
 * Référence à un média image (spec 02 §3.2, spec 06 §6) — une colonne FK
 * simple vers `media`, jamais `cascadeOnDelete` (supprimer un média ne doit
 * jamais supprimer silencieusement le contenu qui le référence). La colonne
 * porte directement la clé du champ, sans suffixe `_id` : contrairement aux
 * relations (spec 02 §5), aucune méthode Eloquent n'est générée pour ce
 * champ, donc pas de collision de nom à éviter.
 */
final class ImageField extends FieldType
{
    public static function key(): string
    {
        return 'image';
    }

    public function columnDefinition(string $name, array $options): string
    {
        return "\$table->foreignId('{$name}')->nullable()->constrained('media')->nullOnDelete();";
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<mixed>
     */
    public function rules(string $name, array $options): array
    {
        return [
            'nullable',
            'integer',
            'exists:media,id',
            function (string $attribute, mixed $value, \Closure $fail) use ($options): void {
                if ($value === null || $value === '') {
                    return;
                }

                $media = Media::find((int) $value);

                if ($media === null) {
                    return;
                }

                if (! $media->isImage()) {
                    $fail('Le média sélectionné n\'est pas une image.');

                    return;
                }

                $width = $media->currentWidth();
                $height = $media->currentHeight();

                if (isset($options['min_width']) && $width !== null && $width < (int) $options['min_width']) {
                    $fail("L'image doit faire au moins {$options['min_width']}px de large.");
                }

                if (isset($options['min_height']) && $height !== null && $height < (int) $options['min_height']) {
                    $fail("L'image doit faire au moins {$options['min_height']}px de haut.");
                }

                if (isset($options['max_width']) && $width !== null && $width > (int) $options['max_width']) {
                    $fail("L'image ne doit pas dépasser {$options['max_width']}px de large.");
                }

                if (isset($options['max_height']) && $height !== null && $height > (int) $options['max_height']) {
                    $fail("L'image ne doit pas dépasser {$options['max_height']}px de haut.");
                }
            },
        ];
    }

    public function cast(array $options): ?string
    {
        return null;
    }

    public function formComponent(): string
    {
        return 'baobab::field.media';
    }

    public function displayComponent(): string
    {
        return 'baobab::field.media-display';
    }

    public function toApi(mixed $value, array $options): mixed
    {
        $media = $value === null ? null : Media::find((int) $value);

        return $media === null ? null : ['id' => $media->id, 'url' => $media->url(), 'alt' => $media->alt];
    }

    public function graphqlType(array $options): string
    {
        return 'Media';
    }

    public function openApiSchema(array $options): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'url' => ['type' => 'string'],
                'alt' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    public function optionsRules(): array
    {
        return [
            'min_width' => ['nullable', 'integer', 'min:1'],
            'min_height' => ['nullable', 'integer', 'min:1'],
            'max_width' => ['nullable', 'integer', 'min:1'],
            'max_height' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
