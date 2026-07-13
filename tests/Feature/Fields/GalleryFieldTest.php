<?php

use Baobab\ContentTypes\Fields\Types\GalleryField;

it('GalleryField has no own column, unlike image/file', function () {
    $field = new GalleryField;

    expect($field->columnDefinition('photos', []))->toBe('')
        ->and($field->cast([]))->toBeNull()
        ->and($field->graphqlType([]))->toBe('[Media]');
});

it('GalleryField requires the submitted value to be an array', function () {
    $field = new GalleryField;

    expect($field->rules('photos', []))->toBe(['array']);
});
