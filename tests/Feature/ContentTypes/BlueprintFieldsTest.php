<?php

use Baobab\ContentTypes\Blueprint\ContentTypeBlueprint;
use Baobab\ContentTypes\Exceptions\InvalidBlueprintException;
use Baobab\ContentTypes\Support\BlueprintFields;

/**
 * Point de résolution unique de ce qu'un blueprint dit de ses champs pour
 * l'affichage (spec 02 §3.1, spec 19 §5.10 amendement n° 17).
 */
it('prefers a declared label over the humanized key', function () {
    expect(BlueprintFields::label(['key' => 'featured_image', 'label' => 'Visuel principal']))
        ->toBe('Visuel principal');
});

it('falls back to the humanized key when no label is declared', function () {
    expect(BlueprintFields::label(['key' => 'featured_image']))->toBe('Featured Image')
        ->and(BlueprintFields::label(['key' => 'featured_image', 'label' => '   ']))->toBe('Featured Image');
});

it('prefers the designated body field over any deduction', function () {
    $blueprint = [
        'body_field' => 'notes',
        'fields' => [
            ['key' => 'intro', 'type' => 'richtext'],
            ['key' => 'notes', 'type' => 'textarea'],
        ],
    ];

    expect(BlueprintFields::body($blueprint)['key'])->toBe('notes');
});

it('deduces the body field from the first richtext, then the first textarea', function () {
    $withRichtext = ['fields' => [
        ['key' => 'title', 'type' => 'text'],
        ['key' => 'summary', 'type' => 'textarea'],
        ['key' => 'body', 'type' => 'richtext'],
    ]];

    // Le cas réel qui a motivé `body_field` : un type dont la prose vit dans
    // un textarea, sans aucun richtext (blueprint `Book` du banc d'essai).
    $withoutRichtext = ['fields' => [
        ['key' => 'title', 'type' => 'text'],
        ['key' => 'summary', 'type' => 'textarea'],
    ]];

    expect(BlueprintFields::body($withRichtext)['key'])->toBe('body')
        ->and(BlueprintFields::body($withoutRichtext)['key'])->toBe('summary')
        ->and(BlueprintFields::body(['fields' => [['key' => 'title', 'type' => 'text']]]))->toBeNull();
});

it('prefers the designated image field over the first image field', function () {
    $blueprint = [
        'image_field' => 'cover',
        'fields' => [
            ['key' => 'author_photo', 'type' => 'image'],
            ['key' => 'cover', 'type' => 'image'],
        ],
    ];

    expect(BlueprintFields::image($blueprint)['key'])->toBe('cover')
        ->and(BlueprintFields::image(['fields' => [['key' => 'author_photo', 'type' => 'image']]])['key'])
        ->toBe('author_photo');
});

it('ignores a designation naming a field that does not exist', function () {
    $blueprint = [
        'body_field' => 'ghost',
        'fields' => [['key' => 'body', 'type' => 'richtext']],
    ];

    expect(BlueprintFields::body($blueprint)['key'])->toBe('body');
});

it('keeps in rest every field no other role consumed, in blueprint order', function () {
    $blueprint = [
        'title_field' => 'title',
        'fields' => [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'cover', 'type' => 'image'],
            ['key' => 'body', 'type' => 'richtext'],
            ['key' => 'price', 'type' => 'decimal'],
            ['key' => 'published_on', 'type' => 'date'],
        ],
    ];

    expect(array_column(BlueprintFields::rest($blueprint), 'key'))->toBe(['price', 'published_on']);
});

it('never exposes in rest a field its type withdrew from the API', function () {
    $blueprint = ['fields' => [
        ['key' => 'reference', 'type' => 'text'],
        ['key' => 'purchase_price', 'type' => 'decimal', 'exposed_in_api' => false],
    ]];

    expect(array_column(BlueprintFields::rest($blueprint), 'key'))->toBe(['reference']);
});

it('keeps only many_to_many relations targeting another Content Type', function () {
    $blueprint = ['relations' => [
        ['key' => 'tags', 'type' => 'many_to_many', 'target' => 'Tag'],
        ['key' => 'comments', 'type' => 'one_to_many', 'target' => 'Comment'],
        ['key' => 'category', 'type' => 'one_to_one', 'target' => 'Category'],
        ['key' => 'reviewers', 'type' => 'many_to_many', 'target' => 'User'],
    ]];

    expect(array_column(BlueprintFields::taxonomies($blueprint), 'key'))->toBe(['tags']);
});

it('accepts a blueprint declaring body_field and image_field', function () {
    $blueprint = ContentTypeBlueprint::fromJson(carBlueprintJson([
        'body_field' => 'description',
        'image_field' => 'photo',
        'fields' => [
            ['key' => 'description', 'type' => 'richtext'],
            ['key' => 'photo', 'type' => 'image'],
        ],
    ]));

    expect($blueprint->key())->toBe('Car');
});

it('rejects a designation naming a missing or wrongly typed field', function (string $designation, string $key) {
    ContentTypeBlueprint::fromJson(carBlueprintJson([
        $designation => $key,
        'fields' => [
            ['key' => 'description', 'type' => 'richtext'],
            ['key' => 'photo', 'type' => 'image'],
        ],
    ]));
})->with([
    ['body_field', 'ghost'],
    ['body_field', 'photo'],
    ['image_field', 'ghost'],
    ['image_field', 'description'],
])->throws(InvalidBlueprintException::class);
