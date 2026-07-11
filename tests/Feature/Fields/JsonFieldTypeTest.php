<?php

use Baobab\ContentTypes\Fields\Types\JsonField;

it('JsonField builds a nullable json column cast to an array', function () {
    $field = new JsonField;

    expect($field->columnDefinition('metadata', []))->toBe("\$table->json('metadata')->nullable();")
        ->and($field->rules('metadata', []))->toBe(['array'])
        ->and($field->cast([]))->toBe('array')
        ->and($field->toApi(null, []))->toBe([])
        ->and($field->graphqlType([]))->toBe('JSON');
});
