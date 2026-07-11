<?php

use Baobab\ContentTypes\Fields\Types\MultiSelectField;
use Baobab\ContentTypes\Fields\Types\RadioField;
use Baobab\ContentTypes\Fields\Types\SelectField;

it('SelectField builds a nullable string column and an in: rule from choices', function () {
    $field = new SelectField;

    expect($field->columnDefinition('status', []))->toBe("\$table->string('status')->nullable();")
        ->and($field->rules('status', ['choices' => ['draft', 'published']]))->toBe(['string', 'in:draft,published'])
        ->and($field->cast([]))->toBeNull();
});

it('RadioField stores the same way as SelectField, with its own component names', function () {
    $field = new RadioField;

    expect($field->columnDefinition('status', []))->toBe("\$table->string('status')->nullable();")
        ->and($field->rules('status', ['choices' => ['yes', 'no']]))->toBe(['string', 'in:yes,no'])
        ->and($field->formComponent())->toBe('baobab::fields.radio')
        ->and($field->formComponent())->not->toBe((new SelectField)->formComponent());
});

it('MultiSelectField builds a nullable json column cast to an array', function () {
    $field = new MultiSelectField;

    expect($field->columnDefinition('tags', []))->toBe("\$table->json('tags')->nullable();")
        ->and($field->rules('tags', []))->toBe(['array'])
        ->and($field->cast([]))->toBe('array')
        ->and($field->graphqlType([]))->toBe('[String]');
});

it('SelectField/RadioField/MultiSelectField require a non-empty choices option', function () {
    foreach ([new SelectField, new RadioField, new MultiSelectField] as $field) {
        expect($field->optionsRules())->toHaveKey('choices');
    }
});
