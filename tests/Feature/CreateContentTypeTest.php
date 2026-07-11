<?php

use Baobab\ContentTypes\Actions\CreateContentType;
use Baobab\ContentTypes\Exceptions\DuplicateContentTypeException;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Models\ContentType;

it('creates a content type from a valid blueprint, deriving the table name', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson());

    expect($contentType)->toBeInstanceOf(ContentType::class)
        ->and($contentType->key)->toBe('Car')
        ->and($contentType->table_name)->toBe('ct_cars')
        ->and($contentType->is_addressable)->toBeFalse()
        ->and($contentType->module_id)->toBeNull()
        ->and($contentType->version)->toBe(1)
        ->and($contentType->blueprint['label']['singular'])->toBe('Voiture');

    expect(ContentType::where('key', 'Car')->exists())->toBeTrue();
});

it('derives a snake_case plural table name from a compound PascalCase key', function () {
    $contentType = app(CreateContentType::class)(carBlueprintJson([
        'key' => 'RealEstateProperty',
        'label' => ['singular' => 'Bien immobilier', 'plural' => 'Biens immobiliers'],
    ]));

    expect($contentType->table_name)->toBe('ct_real_estate_properties');
});

it('refuses to create a content type with a duplicate key', function () {
    app(CreateContentType::class)(carBlueprintJson());

    expect(fn () => app(CreateContentType::class)(carBlueprintJson()))
        ->toThrow(DuplicateContentTypeException::class);
});

it('refuses an invalid blueprint', function () {
    expect(fn () => app(CreateContentType::class)(carBlueprintJson(['key' => 'car'])))
        ->toThrow(InvalidBlueprintException::class);
});
