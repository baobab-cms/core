<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Actions;

use Baobab\ContentTypes\Blueprint\ContentTypeBlueprint;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\ContentTypes\Models\ContentType;
use Illuminate\Support\Str;

/**
 * Persiste un blueprint de Content Type validé (spec 02 §2.1, première moitié
 * du pipeline). Ne génère aucun fichier ni migration — c'est le rôle du
 * moteur de génération de M3 point 1b, qui peuplera `module_id` ensuite.
 */
final class CreateContentType
{
    public function __invoke(string $blueprintJson): ContentType
    {
        $blueprint = ContentTypeBlueprint::fromJson($blueprintJson);

        if (ContentType::where('key', $blueprint->key())->exists()) {
            throw DuplicateContentTypeException::forKey($blueprint->key());
        }

        return ContentType::create([
            'key' => $blueprint->key(),
            'table_name' => self::tableName($blueprint),
            'is_addressable' => $blueprint->isAddressable(),
            'version' => 1,
            'blueprint' => $blueprint->toArray(),
        ]);
    }

    private static function tableName(ContentTypeBlueprint $blueprint): string
    {
        return 'ct_'.Str::snake(Str::plural($blueprint->key()));
    }
}
