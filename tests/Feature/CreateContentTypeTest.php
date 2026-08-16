<?php

use Baobab\ContentTypes\Actions\CreateContentType;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Models\ContentType;

it('creates a content type from a valid blueprint, deriving the table name', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('CreateContentTypeEntry'));

    expect($contentType)->toBeInstanceOf(ContentType::class)
        ->and($contentType->key)->toBe('CreateContentTypeEntry')
        ->and($contentType->table_name)->toBe('ct_create_content_type_entries')
        ->and($contentType->is_addressable)->toBeFalse()
        ->and($contentType->module_id)->toBeNull()
        ->and($contentType->version)->toBe(1)
        ->and($contentType->blueprint['label']['singular'])->toBe('CreateContentTypeEntry');

    expect(ContentType::where('key', 'CreateContentTypeEntry')->exists())->toBeTrue();
});

it('derives a snake_case plural table name from a compound PascalCase key', function () {
    $contentType = app(CreateContentType::class)(contentTypeBlueprintJson('RealEstateProperty', [
        'label' => ['singular' => 'Bien immobilier', 'plural' => 'Biens immobiliers'],
    ]));

    expect($contentType->table_name)->toBe('ct_real_estate_properties');
});

it('refuses to create a content type with a duplicate key', function () {
    app(CreateContentType::class)(contentTypeBlueprintJson('CreateContentTypeEntry'));

    expect(fn () => app(CreateContentType::class)(contentTypeBlueprintJson('CreateContentTypeEntry')))
        ->toThrow(DuplicateContentTypeException::class);
});

it('refuses an invalid blueprint', function () {
    expect(fn () => app(CreateContentType::class)(contentTypeBlueprintJson('CreateContentTypeEntry', ['key' => 'create_content_type_entry'])))
        ->toThrow(InvalidBlueprintException::class);
});
