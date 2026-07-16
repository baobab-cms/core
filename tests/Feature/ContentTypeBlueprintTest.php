<?php

use Baobab\ContentTypes\Blueprint\ContentTypeBlueprint;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Models\ContentType;

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

it('defaults workflow and unpublish_at to their spec-mandated values when undeclared', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson());

    expect($blueprint->workflowEnabled())->toBeFalse()
        ->and($blueprint->unpublishAtEnabled())->toBeTrue();
});

it('accepts explicit workflow and unpublish_at overrides', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson(['workflow' => true, 'unpublish_at' => false]));

    expect($blueprint->workflowEnabled())->toBeTrue()
        ->and($blueprint->unpublishAtEnabled())->toBeFalse();
});

it('defaults revisionsLimit to null (falls back to the global config) and revisionsExcept to an empty list', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson());

    expect($blueprint->revisionsLimit())->toBeNull()
        ->and($blueprint->revisionsExcept())->toBe([]);
});

it('accepts an explicit revisions override', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'revisions' => ['limit' => 10, 'except' => ['view_count']],
    ]));

    expect($blueprint->revisionsLimit())->toBe(10)
        ->and($blueprint->revisionsExcept())->toBe(['view_count']);
});

it('accepts a field of a known type and a relation targeting a known core model', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'relations' => [['key' => 'reviewer', 'type' => 'one_to_many', 'target' => 'User']],
        'is_addressable' => true,
        'title_field' => 'brand',
    ]));

    expect($blueprint->fields())->toBe([['key' => 'brand', 'type' => 'text']])
        ->and($blueprint->relations())->toBe([['key' => 'reviewer', 'type' => 'one_to_many', 'target' => 'User']])
        ->and($blueprint->isAddressable())->toBeTrue()
        ->and($blueprint->titleField())->toBe('brand');
});

it('rejects an addressable content type without title_field', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'is_addressable' => true,
    ])))->toThrow(InvalidBlueprintException::class);
});

it('rejects a title_field that does not reference a declared field', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'is_addressable' => true,
        'title_field' => 'does_not_exist',
    ])))->toThrow(InvalidBlueprintException::class);
});

it('rejects a title_field pointing at a non-text-ish field type', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'is_featured', 'type' => 'boolean']],
        'is_addressable' => true,
        'title_field' => 'is_featured',
    ])))->toThrow(InvalidBlueprintException::class);
});

it('rejects a relation of an unknown type', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'relations' => [['key' => 'reviewer', 'type' => 'has_many', 'target' => 'User']],
    ])))->toThrow(InvalidBlueprintException::class);
});

it('rejects a relation targeting an unresolvable target', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'relations' => [['key' => 'brand', 'type' => 'one_to_many', 'target' => 'DoesNotExist']],
    ])))->toThrow(InvalidBlueprintException::class);
});

it('rejects a field of an unknown type', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'does_not_exist']],
    ])))->toThrow(InvalidBlueprintException::class);
});

it("rejects a field whose options fail its type's optionsRules", function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'status', 'type' => 'select', 'options' => []]],
    ])))->toThrow(InvalidBlueprintException::class);
});

it('accepts a field whose options satisfy its type optionsRules', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published']]]],
    ]));

    expect($blueprint->fields())->toBe([
        ['key' => 'status', 'type' => 'select', 'options' => ['choices' => ['draft', 'published']]],
    ]);
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

it('defaults url_prefix to the kebab-plural of the key for an addressable type', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'is_addressable' => true,
        'title_field' => 'brand',
    ]));

    expect($blueprint->urlPrefix())->toBe('cars');
});

it('accepts an explicit url_prefix override', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'is_addressable' => true,
        'title_field' => 'brand',
        'url_prefix' => 'voitures',
    ]));

    expect($blueprint->urlPrefix())->toBe('voitures');
});

it('rejects a url_prefix reserved for the admin path', function () {
    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'is_addressable' => true,
        'title_field' => 'brand',
        'url_prefix' => 'admin',
    ])))->toThrow(InvalidBlueprintException::class);
});

it('rejects a url_prefix already used by another addressable Content Type', function () {
    ContentType::create([
        'key' => 'Brand',
        'table_name' => 'ct_brands',
        'is_addressable' => true,
        'version' => 1,
        'blueprint' => ['key' => 'Brand', 'label' => ['singular' => 'Marque', 'plural' => 'Marques'], 'is_addressable' => true, 'url_prefix' => 'voitures'],
    ]);

    expect(fn () => ContentTypeBlueprint::fromJson(carBlueprintJson([
        'fields' => [['key' => 'brand', 'type' => 'text']],
        'is_addressable' => true,
        'title_field' => 'brand',
        'url_prefix' => 'voitures',
    ])))->toThrow(InvalidBlueprintException::class);
});

it('does not consider a non-addressable url_prefix reserved or unique', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson(['url_prefix' => 'admin']));

    expect($blueprint->isAddressable())->toBeFalse();
});
