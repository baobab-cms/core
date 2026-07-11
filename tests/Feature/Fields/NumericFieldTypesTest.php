<?php

use Baobab\ContentTypes\Fields\Types\BooleanField;
use Baobab\ContentTypes\Fields\Types\DecimalField;
use Baobab\ContentTypes\Fields\Types\IntegerField;

// ── integer ──────────────────────────────────────────────────────────────────

it('IntegerField builds a plain signed integer column by default', function () {
    $field = new IntegerField;

    expect($field->columnDefinition('year', []))->toBe("\$table->integer('year');")
        ->and($field->rules('year', []))->toBe(['integer'])
        ->and($field->cast([]))->toBe('integer')
        ->and($field->graphqlType([]))->toBe('Int');
});

it('IntegerField honors unsigned and big options', function () {
    $field = new IntegerField;

    expect($field->columnDefinition('mileage', ['unsigned' => true]))->toBe("\$table->unsignedInteger('mileage');")
        ->and($field->columnDefinition('views', ['big' => true]))->toBe("\$table->bigInteger('views');")
        ->and($field->columnDefinition('hits', ['unsigned' => true, 'big' => true]))->toBe("\$table->unsignedBigInteger('hits');");
});

it('IntegerField adds min/max validation rules when declared', function () {
    $field = new IntegerField;

    expect($field->rules('year', ['min' => 1900, 'max' => 2100]))->toBe(['integer', 'min:1900', 'max:2100']);
});

// ── decimal ──────────────────────────────────────────────────────────────────

it('DecimalField builds a decimal column with default precision/scale', function () {
    $field = new DecimalField;

    expect($field->columnDefinition('price', []))->toBe("\$table->decimal('price', 10, 2);")
        ->and($field->cast([]))->toBe('decimal:2')
        ->and($field->rules('price', []))->toBe(['numeric'])
        ->and($field->graphqlType([]))->toBe('Float');
});

it('DecimalField honors declared precision and scale', function () {
    $field = new DecimalField;

    expect($field->columnDefinition('price', ['precision' => 8, 'scale' => 3]))->toBe("\$table->decimal('price', 8, 3);")
        ->and($field->cast(['scale' => 3]))->toBe('decimal:3');
});

// ── boolean ──────────────────────────────────────────────────────────────────

it('BooleanField builds a boolean column defaulting to false', function () {
    $field = new BooleanField;

    expect($field->columnDefinition('is_featured', []))->toBe("\$table->boolean('is_featured')->default(false);")
        ->and($field->rules('is_featured', []))->toBe(['boolean'])
        ->and($field->cast([]))->toBe('boolean')
        ->and($field->toApi(1, []))->toBeTrue()
        ->and($field->graphqlType([]))->toBe('Boolean');
});
