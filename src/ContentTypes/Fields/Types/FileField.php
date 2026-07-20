<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Fields\Types;

use Baobab\ContentTypes\Fields\FieldType;
use Baobab\Media\Models\Media;

/**
 * Référence à un média quelconque (spec 02 §3.2, spec 06 §6) — même patron
 * qu'`ImageField`, sans la contrainte « doit être une image » : un champ
 * `file` accepte tout type de média, éventuellement restreint par
 * `options['mime_types']`.
 */
final class FileField extends FieldType
{
    public static function key(): string
    {
        return 'file';
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

                /** @var list<string> $mimeTypes */
                $mimeTypes = (array) ($options['mime_types'] ?? []);

                if ($mimeTypes !== [] && ! in_array($media->mime_type, $mimeTypes, true)) {
                    $fail('Le type de fichier sélectionné n\'est pas autorisé pour ce champ.');
                }

                if (isset($options['max_size']) && $media->size > (int) $options['max_size']) {
                    $fail('Le fichier sélectionné dépasse la taille maximale autorisée.');
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

        return $media === null ? null : ['id' => $media->id, 'url' => $media->url(), 'file_name' => $media->file_name];
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
                'file_name' => ['type' => 'string'],
            ],
        ];
    }

    public function optionsRules(): array
    {
        return [
            'mime_types' => ['nullable', 'array'],
            'mime_types.*' => ['string'],
            'max_size' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
