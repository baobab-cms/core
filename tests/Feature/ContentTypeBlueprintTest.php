<?php

use Baobab\ContentTypes\Blueprint\ContentTypeBlueprint;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;

it('accepts a minimal valid blueprint', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson());

    expect($blueprint->key())->toBe('Car')
        ->and($blueprint->labelSingular())->toBe('Voiture')
        ->and($blueprint->labelPlural())->toBe('Voitures')
        ->and($blueprint->isAddressable())->toBeFalse()
        ->and($blueprint->blueprintVersion())->toBe(1)
        ->and($blueprint->fields())->toBe([])
        ->and($blueprint->relations())->toBe([]);
});

it('accepts non-empty fields and relations without deeply validating them', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'relations' => [['key' => 'brand', 'type' => 'belongs_to']],
        'is_addressable' => true,
    ]));

    expect($blueprint->fields())->toBe([['key' => 'brand', 'type' => 'text']])
        ->and($blueprint->relations())->toBe([['key' => 'brand', 'type' => 'belongs_to']])
        ->and($blueprint->isAddressable())->toBeTrue();
});

it('rejects a key that is not PascalCase', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson(['key' => 'car'])))
        ->toThrow(InvalidBlueprintException::class);
});

it('rejects a blueprint without a label', function () {
    $json = (string) json_encode(['key' => 'Car']);

    expect(fn () => ContentTypeBlueprint::fromJson($json))
        ->toThrow(InvalidBlueprintException::class);
});

it('rejects malformed JSON', function () {
    expect(fn () => ContentTypeBlueprint::fromJson('{not json'))
        ->toThrow(InvalidBlueprintException::class);
});
